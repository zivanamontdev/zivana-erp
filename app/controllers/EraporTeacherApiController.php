<?php

/** JSON adapter for the opt-in new workflow; legacy routes remain unchanged. */
class EraporTeacherApiController extends Controller
{
    public function __construct()
    {
        // JSON equivalent of parent's CSRF guard: do not emit HTML/redirects to fetch callers.
        header('Cache-Control: no-store');
        if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET',['POST','PUT','DELETE','PATCH'],true)) {
            $token=$_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            if (!is_string($token) || !validateCsrfToken($token)) $this->fail(419,'CSRF_INVALID','Token keamanan tidak valid. Muat ulang halaman.');
        }
    }
    public function show(string $id): void
    {
        $this->execute('lihat',fn($db,$actor)=>EraporTeacherForm::read($db,$this->id($id),$actor));
    }
    public function save(string $id,string $documentId): void
    {
        $this->execute('edit',function($db,$actor) use($id,$documentId) {
            $sid=$this->id($id); $did=$this->id($documentId);
            $body=$this->body(['changes']);
            if (!is_array($body['changes'] ?? null) || !array_is_list($body['changes']) || count($body['changes'])>200) throw new InvalidArgumentException('changes harus berupa daftar, maksimal 200 isian.');
            // Read model authorizes session and membership before choosing a trusted service.
            $form=EraporTeacherForm::read($db,$sid,$actor);
            $type=null;
            foreach ($form['documents'] as $doc) if ((int)$doc['id']===$did) $type=$doc['jenis_dokumen'];
            if ($type===null) throw new DomainException('Dokumen tidak tersedia dalam sesi.');
            foreach ($body['changes'] as $change) {
                $allowed=$type==='RTS'?['indikator_id','nilai','expected']:['key','value','expected'];
                if (!is_array($change) || array_diff(array_keys($change),$allowed) || array_diff($allowed,array_keys($change))) throw new InvalidArgumentException('Struktur isian tidak valid.');
            }
            $saved = match ($type) {
                'RTS'=>EraporRtsEntry::save($db,$sid,$did,$actor,$body['changes']),
                'BING','PPI'=>EraporStructuredEntry::save($db,$type,$sid,$did,$actor,$body['changes']),
                'AGAMA'=>EraporAgamaEntry::save($db,$sid,$did,$actor,$body['changes']),
                'UMMI'=>EraporUmmiEntry::save($db,$sid,$did,$actor,$body['changes']),
                default=>throw new DomainException('Rubrik belum didukung.'),
            };
            // Send authoritative session completion/capabilities after the atomic entry save.
            // The browser may display progress, but never decides whether submission is allowed.
            $state=EraporTeacherForm::read($db,$sid,$actor);
            $saved['completion']=$state['completion'];
            $saved['capabilities']=$state['capabilities'];
            $saved['session']=$state['session'];
            return $saved;
        });
    }
    public function confirmFilled(string $id): void
    {
        $this->execute('kirim',function($db,$actor) use($id) {
            $this->body([]);
            return EraporConfirmFilled::confirm($db,$this->id($id),$actor);
        });
    }
    public function confirmReception(string $id): void
    {
        $this->execute('kirim',function($db,$actor) use($id) {
            $this->body([]);
            return EraporConfirmReception::confirm($db,$this->id($id),$actor);
        });
    }
    private function execute(string $permission,callable $action): void
    {
        try {
            $actor=(int)($_SESSION['user_id'] ?? 0);
            if ($actor<1) $this->fail(401,'AUTH_REQUIRED','Silakan login terlebih dahulu.');
            $user=(new User())->find($actor);
            if (!$user || !AccountAccess::active($user)) $this->fail(401,'AUTH_REQUIRED','Akun tidak aktif. Silakan login kembali.');
            if (!(new RoleMiddleware())->check('Portal Guru','Daftar Murid',$permission)) $this->fail(403,'FORBIDDEN','Anda tidak memiliki izin untuk fitur ini.');
            if (!defined('ERAPOR_API_ENABLED') || !ERAPOR_API_ENABLED) $this->fail(503,'ERAPOR_NOT_ENABLED','Alur rapor baru belum diaktifkan.');
            $data=$action(Database::getInstance(),$actor);
        } catch (InvalidArgumentException|JsonException $e) {
            $this->fail(422,'INVALID_INPUT','Payload atau identitas request tidak valid.');
        } catch (DomainException $e) {
            // Generic conflict avoids exposing another student's identity or state.
            $this->fail(409,'REQUEST_REJECTED','Permintaan tidak dapat diproses. Periksa akses, status, dan isian lalu muat ulang.');
        } catch (Throwable $e) {
            // No raw SQL, pupil values, signatures or credentials in response/log.
            error_log('Erapor teacher API failure: '.get_class($e));
            $this->fail(503,'ERAPOR_UNAVAILABLE','Layanan rapor belum tersedia. Silakan coba lagi.');
        }
        $this->json(['ok'=>true,'data'=>$data,'csrf_token'=>getCsrfToken()]);
    }
    private function id(string $value): int
    {
        if (!preg_match('/^[1-9][0-9]*$/D',$value) || filter_var($value,FILTER_VALIDATE_INT)===false) throw new InvalidArgumentException('ID tidak valid.');
        return (int)$value;
    }
    protected function rawBody(): string
    {
        return (string)file_get_contents('php://input',false,null,0,1048577);
    }
    private function body(array $allowed): array
    {
        if (strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE'] ?? '')[0]))!=='application/json') throw new InvalidArgumentException('JSON diperlukan.');
        $raw=$this->rawBody();
        if (strlen($raw)>1048576) throw new InvalidArgumentException('Payload terlalu besar.');
        $object=json_decode($raw,false,32,JSON_THROW_ON_ERROR);
        if (!$object instanceof stdClass) throw new InvalidArgumentException('Objek JSON diperlukan.');
        if (property_exists($object,'changes') && !is_array($object->changes)) throw new InvalidArgumentException('changes harus array JSON.');
        $body=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
        if (array_diff(array_keys($body),$allowed) || array_diff($allowed,array_keys($body))) throw new InvalidArgumentException('Field tidak dikenal/tidak lengkap.');
        return $body;
    }
    private function fail(int $status,string $code,string $message): void
    {
        $this->json(['ok'=>false,'error'=>['code'=>$code,'message'=>$message],'csrf_token'=>getCsrfToken()],$status);
    }
}

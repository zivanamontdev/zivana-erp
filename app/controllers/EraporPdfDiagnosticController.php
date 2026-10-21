<?php

/** Temporary Superadmin-only, token/CSRF protected diagnostics. Never serves a rebuilt official PDF. */
final class EraporPdfDiagnosticController extends Controller
{
    public function index(): void
    {
        $this->guard(); $this->render(null);
    }

    public function run(): void
    {
        $this->guard();
        if (!hash_equals(self::token(), $this->value('token'))) {
            http_response_code(403); $this->render(null,'Token diagnosis tidak sesuai.'); return;
        }
        if ((int)($_SESSION['pdf_diag_next'] ?? 0)>time()) {
            http_response_code(429); $this->render(null,'Tunggu 10 detik sebelum menjalankan pemeriksaan berikutnya.'); return;
        }
        $_SESSION['pdf_diag_next']=time()+10;
        // Free a small reserve on fatal/OOM. Shutdown diagnostics contain no raw trace arguments or report values.
        $reserve=str_repeat('x',262144);
        register_shutdown_function(static function() use (&$reserve): void {
            $last=error_get_last();
            if (!$last || !in_array($last['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR],true)) return;
            $reserve='';
            while(ob_get_level()>0) ob_end_clean();
            http_response_code(500);
            header('Content-Type: application/json; charset=UTF-8');
            header('Cache-Control: private, no-store');
            header('Content-Disposition: attachment; filename="diagnosis-pdf-fatal.json"');
            echo json_encode(['format'=>1,'ok'=>false,'read_only'=>true,'fatal'=>true,
                'error'=>EraporPdfDiagnostics::safeError(new ErrorException($last['message'],0,$last['type'],$last['file'],$last['line']))],JSON_INVALID_UTF8_SUBSTITUTE|JSON_PRETTY_PRINT);
        });
        try {
            $report=EraporPdfDiagnostics::run(Database::getInstance(),(int)$_SESSION['user_id'],[
                'mode'=>$this->value('mode'),'session_id'=>$this->value('session_id'),'actor_id'=>$this->value('actor_id'),
                'document_type'=>$this->value('document_type'),'semester'=>$this->value('semester')]);
        } catch (Throwable $error) {
            $report=['format'=>1,'id'=>bin2hex(random_bytes(12)),'created_at'=>gmdate('c'),'ok'=>false,'read_only'=>true,
                'mode'=>$this->value('mode'),'steps'=>[],'error'=>EraporPdfDiagnostics::safeError($error)];
        }
        $reserve='';
        $_SESSION['pdf_diag_next']=time()+10;
        $_SESSION['pdf_diag_report']=['expires'=>time()+900,'report'=>$report];
        $this->render($report);
    }

    public function download(): void
    {
        $this->guard();
        $saved=$_SESSION['pdf_diag_report'] ?? null;
        if (!is_array($saved) || ($saved['expires']??0)<time() || !hash_equals((string)($saved['report']['id']??''),$this->value('report_id'))) $this->missing();
        header('Content-Type: application/json; charset=UTF-8');
        header('X-CSRF-Token: '.getCsrfToken());
        header('Content-Disposition: attachment; filename="diagnosis-pdf.json"');
        echo json_encode($saved['report'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
    }

    private function guard(): void
    {
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Robots-Tag: noindex, nofollow');
        header('Referrer-Policy: no-referrer');
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        if (strlen(self::token())<32 || !defined('ERAPOR_API_ENABLED') || !ERAPOR_API_ENABLED) $this->missing();
        $this->middleware(AuthMiddleware::class);
        $q=Database::getInstance()->prepare('SELECT r.nama FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? AND u.is_active=1');
        $q->execute([(int)($_SESSION['user_id']??0)]);
        if ($q->fetchColumn()!=='Superadmin') $this->missing();
    }

    private static function token(): string
    {
        return defined('ERAPOR_PDF_DIAGNOSTIC_TOKEN')?(string)ERAPOR_PDF_DIAGNOSTIC_TOKEN:'';
    }

    private function value(string $key): string
    {
        $value=$this->input($key,'');
        return is_string($value)?substr(trim($value),0,256):'';
    }

    private function missing(): never
    {
        http_response_code(404);require VIEW_PATH.'/errors/404.php';exit;
    }

    private function render(?array $report,?string $notice=null): void
    {
        $q=Database::getInstance()->query('SELECT u.id,u.email,k.nama FROM users u LEFT JOIN karyawan k ON k.id=u.karyawan_id WHERE u.is_active=1 ORDER BY k.nama,u.email');
        $actors=[''=>'Pilih akun yang mengalami masalah'];
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row) $actors[$row['id']]=trim((string)($row['nama']??'')).' · '.$row['email'];
        $this->view('admin.erapor-diagnostics.index',[
            'pageTitle'=>'Diagnosis PDF eRapor','breadcrumb'=>breadcrumb('Sistem','Diagnosis PDF'),'activeNavItem'=>'',
            'report'=>$report,'notice'=>$notice,'actors'=>$actors]);
    }
}

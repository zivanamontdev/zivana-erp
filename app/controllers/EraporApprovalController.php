<?php

/** HTTP surface for the opt-in, assignment-scoped eRapor approval flow. */
final class EraporApprovalController extends Controller
{
    public function index(): void
    {
        if (!$this->enabled()) return;
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'eRapor', 'Persetujuan', 'lihat');

        $actorId = (int) $_SESSION['user_id'];
        $inbox = EraporApprovalInbox::read(Database::getInstance(), $actorId);
        $this->view('admin.erapor-approval.index', [
            'pageTitle' => 'Antrean Persetujuan',
            'breadcrumb' => breadcrumb(['Persetujuan eRapor', '/erapor/persetujuan'], 'Antrean'),
            'activeNavItem' => 'erapor-approval',
            'tasks' => $inbox['tasks'],
            'notice' => $this->takeNotice(),
        ]);
    }

    public function review(string $sessionId, string $approvalId): void
    {
        if (!$this->enabled()) return;
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'eRapor', 'Persetujuan', 'lihat');
        if (!$this->validId($sessionId) || !$this->validId($approvalId)) {
            $this->notFound();
            return;
        }

        try {
            $review = EraporApprovalReview::read(Database::getInstance(), (int) $sessionId, (int) $approvalId, (int) $_SESSION['user_id']);
        } catch (DomainException $exception) {
            // Assigned-user and document-scope failures must not disclose whether another task exists.
            $this->notFound();
            return;
        }

        $canApprove = (new RoleMiddleware())->check('eRapor', 'Persetujuan', 'edit');
        $canApprove = $canApprove && $review['can_approve'];
        $this->view('admin.erapor-approval.review', [
            'pageTitle' => 'Tinjau Persetujuan',
            'breadcrumb' => breadcrumb(['Persetujuan eRapor', '/erapor/persetujuan'], 'Tinjau'),
            'activeNavItem' => 'erapor-approval',
            'review' => $review,
            'canApprove' => $canApprove,
            'notice' => $this->takeNotice(),
        ]);
    }

    public function approve(string $sessionId, string $approvalId): void
    {
        if (!$this->enabled()) return;
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'eRapor', 'Persetujuan', 'edit');
        if (!$this->validId($sessionId) || !$this->validId($approvalId)) {
            $this->notFound();
            return;
        }

        try {
            $result = EraporApprove::approve(Database::getInstance(), (int) $sessionId, (int) $approvalId, (int) $_SESSION['user_id']);
            $_SESSION['erapor_approval_notice'] = [
                'type' => 'success',
                'message' => $result['all_approved']
                    ? 'Seluruh persetujuan tercatat. Rapor menunggu proses publikasi dan belum diterbitkan.'
                    : 'Persetujuan Anda berhasil dicatat.',
            ];
        } catch (DomainException $exception) {
            $_SESSION['erapor_approval_notice'] = [
                'type' => 'error',
                'message' => 'Persetujuan tidak dapat diproses. Periksa urutan, penugasan, dan status rapor.',
            ];
        }

        $this->redirect('/erapor/persetujuan/' . (int) $sessionId . '/' . (int) $approvalId);
    }

    private function enabled(): bool
    {
        if (defined('ERAPOR_API_ENABLED') && ERAPOR_API_ENABLED) return true;
        $this->notFound();
        return false;
    }

    private function validId(string $id): bool
    {
        return preg_match('/^[1-9][0-9]*$/D', $id) === 1 && filter_var($id, FILTER_VALIDATE_INT) !== false;
    }

    private function takeNotice(): ?array
    {
        $notice = $_SESSION['erapor_approval_notice'] ?? null;
        unset($_SESSION['erapor_approval_notice']);
        return is_array($notice) && isset($notice['type'], $notice['message']) ? $notice : null;
    }

    private function notFound(): void
    {
        http_response_code(404);
        require VIEW_PATH . '/errors/404.php';
    }
}

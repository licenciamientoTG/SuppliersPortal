<?php

namespace App\Http\Controllers;

use App\Services\NotificationCenterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationCenterService $notificationCenter
    ) {}

    public function index(Request $request): View
    {
        $notifications = $this->notificationCenter
            ->queryForUser($this->resolveAuthenticatable($request))
            ->latest()
            ->paginate(20);

        return view('notifications.index', compact('notifications'));
    }

    public function open(Request $request, string $notification): RedirectResponse
    {
        $notificationModel = $this->notificationCenter->findForUser($this->resolveAuthenticatable($request), $notification);

        abort_unless($notificationModel, 404);

        if ($notificationModel->read_at === null) {
            $notificationModel->markAsRead();
        }

        $targetUrl = $notificationModel->data['url'] ?? route('notifications.index');

        return redirect()->to($targetUrl);
    }

    public function markAsRead(Request $request, string $notification): RedirectResponse
    {
        $notificationModel = $this->notificationCenter->findForUser($this->resolveAuthenticatable($request), $notification);

        abort_unless($notificationModel, 404);

        if ($notificationModel->read_at === null) {
            $notificationModel->markAsRead();
        }

        return back()->with('status', 'Notificación marcada como leída.');
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $this->notificationCenter->markAllAsReadForUser($this->resolveAuthenticatable($request));

        return back()->with('status', 'Todas las notificaciones fueron marcadas como leídas.');
    }
    private function resolveAuthenticatable(Request $request): mixed
    {
        return $request->user('supplier') ?? $request->user('web') ?? $request->user();
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\WhatsAppInteractivePreviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WhatsAppInteractivePreviewController extends Controller
{
    public function index()
    {
        return view('whatsapp.interactive-preview');
    }

    public function sendButtons(Request $request, WhatsAppInteractivePreviewService $service): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:60'],
        ]);

        try {
            $response = $service->sendButtonsPreview($validated['phone']);

            return back()->with([
                'preview_success' => 'Previa com buttons enviada com sucesso.',
                'preview_response' => json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable $e) {
            return back()->withInput()->with('preview_error', $e->getMessage());
        }
    }

    public function sendList(Request $request, WhatsAppInteractivePreviewService $service): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:60'],
        ]);

        try {
            $response = $service->sendListPreview($validated['phone']);

            return back()->with([
                'preview_success' => 'Previa com list message enviada com sucesso.',
                'preview_response' => json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable $e) {
            return back()->withInput()->with('preview_error', $e->getMessage());
        }
    }

    public function sendBooleanButtons(Request $request, WhatsAppInteractivePreviewService $service): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:60'],
        ]);

        try {
            $response = $service->sendBooleanButtonsPreview($validated['phone']);

            return back()->with([
                'preview_success' => 'Previa de confirmacao Sim/Nao enviada com sucesso.',
                'preview_response' => json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable $e) {
            return back()->withInput()->with('preview_error', $e->getMessage());
        }
    }
}

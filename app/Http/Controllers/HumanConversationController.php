<?php

namespace App\Http\Controllers;

use App\Models\HumanConversation;
use App\Services\HumanConversationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HumanConversationController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $search = trim($request->string('search')->toString());

        $conversations = HumanConversation::query()
            ->with(['customer', 'assignedUser'])
            ->when($status !== '', function ($query) use ($status) {
                $query->where('status', $status);
            }, function ($query) {
                $query->whereIn('status', [
                    HumanConversation::STATUS_OPEN,
                    HumanConversation::STATUS_IN_PROGRESS,
                ]);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('phone', 'like', '%' . $search . '%')
                        ->orWhere('contact_name', 'like', '%' . $search . '%')
                        ->orWhereHas('customer', function ($customerQuery) use ($search) {
                            $customerQuery->where('name', 'like', '%' . $search . '%');
                        });
                });
            })
            ->orderByRaw('case when status = ? then 0 when status = ? then 1 else 2 end', [
                HumanConversation::STATUS_OPEN,
                HumanConversation::STATUS_IN_PROGRESS,
            ])
            ->orderByDesc('last_message_at')
            ->paginate(25)
            ->withQueryString();

        $selectedConversation = $conversations->first();

        if ($request->filled('conversation')) {
            $selectedConversation = HumanConversation::query()
                ->with(['customer', 'assignedUser', 'messages.senderUser'])
                ->find($request->integer('conversation'));
        } elseif ($selectedConversation) {
            $selectedConversation->load(['messages.senderUser']);
        }

        return view('human-conversations.index', [
            'conversations' => $conversations,
            'selectedConversation' => $selectedConversation,
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
        ]);
    }

    public function show(HumanConversation $humanConversation, Request $request): View
    {
        $status = $request->string('status')->toString();
        $search = trim($request->string('search')->toString());

        $conversations = HumanConversation::query()
            ->with(['customer', 'assignedUser'])
            ->when($status !== '', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('phone', 'like', '%' . $search . '%')
                        ->orWhere('contact_name', 'like', '%' . $search . '%');
                });
            })
            ->orderByDesc('last_message_at')
            ->paginate(25)
            ->withQueryString();

        $humanConversation->load(['customer', 'assignedUser', 'messages.senderUser']);

        return view('human-conversations.index', [
            'conversations' => $conversations,
            'selectedConversation' => $humanConversation,
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
        ]);
    }

    public function assign(HumanConversation $humanConversation, Request $request, HumanConversationService $service): RedirectResponse
    {
        $service->assignConversation($humanConversation, $request->user());

        return redirect()
            ->route('human-conversations.show', $humanConversation)
            ->with('success', 'Atendimento assumido com sucesso.');
    }

    public function sendMessage(HumanConversation $humanConversation, Request $request, HumanConversationService $service): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $service->sendMessage($humanConversation, $request->user(), $validated['message']);

        return redirect()
            ->route('human-conversations.show', $humanConversation)
            ->with('success', 'Mensagem enviada para o cliente.');
    }

    public function close(HumanConversation $humanConversation, Request $request, HumanConversationService $service): RedirectResponse
    {
        $service->closeConversation($humanConversation, $request->user(), $request->boolean('release_bot', true));

        return redirect()
            ->route('human-conversations.index')
            ->with('success', 'Atendimento encerrado.');
    }

    public function reopen(HumanConversation $humanConversation, Request $request, HumanConversationService $service): RedirectResponse
    {
        $service->reopenConversation($humanConversation, $request->user(), 30);

        return redirect()
            ->route('human-conversations.show', $humanConversation)
            ->with('success', 'Atendimento reaberto.');
    }
}

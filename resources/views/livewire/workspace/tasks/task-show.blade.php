@php($tz = config('campaign.display_timezone'))
@php($status = $task->status->value)
<div>
    <x-ui.page-header :title="$task->title" :eyebrow="__('tasks.task')">
        <x-slot:actions>
            <x-ui.badge :tone="$task->priority->tone()">{{ $task->priority->label() }}</x-ui.badge>
            <x-ui.badge :tone="$task->status->tone()" dot>{{ $task->status->label() }}</x-ui.badge>
            @if ($canWork && ! $task->status->isClosed())
                @if ($status !== 'in_progress')
                    <x-ui.button variant="secondary" icon="zap" wire:click="setStatus('in_progress')" loading="setStatus">{{ __('tasks.start') }}</x-ui.button>
                @endif
                @if ($status !== 'blocked')
                    <x-ui.button variant="ghost" icon="circle-alert" wire:click="setStatus('blocked')">{{ __('tasks.mark_blocked') }}</x-ui.button>
                @endif
                @if ($isAssignee || $canManage)
                    <x-ui.button icon="check" x-on:click="$dispatch('open-modal', 'complete-task')">{{ __('tasks.complete') }}</x-ui.button>
                @endif
            @elseif ($canWork && $task->status->isClosed())
                <x-ui.button variant="secondary" icon="rotate-ccw" wire:click="setStatus('open')" loading="setStatus">{{ __('tasks.reopen') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($task->isOverdue())
        <x-ui.alert tone="danger" icon="clock" class="mb-6">{{ __('tasks.overdue_banner', ['date' => $task->due_at->timezone($tz)->format('j M Y, H:i')]) }}</x-ui.alert>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px] [&>*]:min-w-0">
        <div class="space-y-6">
            <x-ui.card :title="__('tasks.details')">
                @if ($task->description)
                    <p class="whitespace-pre-line text-sm leading-6 text-fg">{{ $task->description }}</p>
                @else
                    <p class="text-sm text-subtle">{{ __('tasks.no_description') }}</p>
                @endif
                @if ($task->completion_note)
                    <x-ui.alert tone="success" :title="__('tasks.completion_note')" class="mt-4">{{ $task->completion_note }}</x-ui.alert>
                @endif
            </x-ui.card>

            <x-ui.card :title="__('tasks.activity')" :padding="false">
                @if ($canWork)
                    <form wire:submit="addComment" class="flex flex-col gap-2 border-b border-line px-5 py-4 sm:flex-row sm:items-start">
                        <label for="task-comment" class="sr-only">{{ __('tasks.comment') }}</label>
                        <textarea id="task-comment" wire:model="comment" rows="2" placeholder="{{ __('tasks.comment_placeholder') }}" class="form-control flex-1" @error('comment') aria-invalid="true" @enderror></textarea>
                        <x-ui.button type="submit" variant="secondary" icon="message-square" loading="addComment">{{ __('tasks.post') }}</x-ui.button>
                    </form>
                @endif
                <ol class="divide-y divide-line">
                    @foreach ($timeline as $row)
                        @php($item = $row['item'])
                        <li class="flex gap-3 px-5 py-3.5" wire:key="{{ $row['kind'] }}-{{ $item->id }}">
                            @if ($row['kind'] === 'comment')
                                <x-ui.avatar :name="$item->user->name" size="sm" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs text-muted"><span class="font-semibold text-fg">{{ $item->user->name }}</span> · {{ $item->created_at->timezone($tz)->format('j M, H:i') }}</p>
                                    <p class="mt-1 whitespace-pre-line text-sm text-fg">{{ $item->body }}</p>
                                </div>
                            @else
                                <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-surface-sunken text-muted">
                                    <x-icon :name="match ($item->type->value) { 'created' => 'plus', 'assigned' => 'user-plus', 'status_changed' => 'circle-dot', 'evidence_added' => 'paperclip', 'escalated' => 'triangle-alert', 'reminded' => 'bell', 'due_changed' => 'calendar', default => 'activity' }" class="size-3.5" />
                                </span>
                                <p class="min-w-0 flex-1 pt-1 text-sm text-muted">
                                    <span class="text-fg">{{ $item->user?->name ?? __('tasks.system') }}</span>
                                    {{ __('tasks.event_'.$item->type->value, [
                                        'to' => $item->data['to_name'] ?? '',
                                        'status' => \App\Enums\TaskStatus::tryFrom($item->data['to'] ?? '')?->label() ?? '',
                                        'file' => $item->data['file'] ?? '',
                                        'reason' => $item->data['reason'] ?? '',
                                        'level' => $item->data['level'] ?? '',
                                    ]) }}
                                    <span class="text-xs text-subtle">· {{ $item->created_at->timezone($tz)->format('j M, H:i') }}</span>
                                </p>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card>
                <dl class="space-y-3 text-sm">
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-muted">{{ __('tasks.assignee') }}</dt>
                        <dd class="flex items-center gap-2 text-right text-fg">
                            {{ $task->assignee?->name ?? __('tasks.unassigned') }}
                            @if ($canManage && ! $task->status->isClosed())
                                <x-ui.button size="sm" variant="ghost" x-on:click="$dispatch('open-modal', 'assign-task')">{{ $task->assignee ? __('tasks.reassign') : __('tasks.assign') }}</x-ui.button>
                            @endif
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-muted">{{ __('tasks.due_at') }}</dt>
                        <dd class="flex items-center gap-2 text-right text-fg">
                            {{ $task->due_at?->timezone($tz)->format('j M Y, H:i') ?? '—' }}
                            @if ($canManage && ! $task->status->isClosed())
                                <x-ui.button size="sm" variant="ghost" square icon="pencil" x-on:click="$dispatch('open-modal', 'task-due')"><span class="sr-only">{{ __('tasks.change_due') }}</span></x-ui.button>
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('supporters.area') }}</dt><dd class="text-right text-fg">{{ $task->areaLabel() }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('tasks.created_by') }}</dt><dd class="text-right text-fg">{{ $task->creator?->name ?? '—' }} <span class="block text-xs text-subtle">{{ $task->created_at->timezone($tz)->format('j M Y, H:i') }}</span></dd></div>
                    @if ($task->escalation_level > 0)
                        <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('tasks.escalation') }}</dt><dd class="text-right"><x-ui.badge tone="danger" size="sm">{{ __('tasks.level', ['level' => $task->escalation_level]) }}</x-ui.badge></dd></div>
                    @endif
                </dl>
                @if ($canWork && ! $task->status->isClosed())
                    <div class="mt-4 border-t border-line pt-4">
                        <x-ui.button variant="ghost" size="sm" icon="triangle-alert" class="w-full" x-on:click="$dispatch('open-modal', 'escalate-task')">{{ __('tasks.escalate') }}</x-ui.button>
                    </div>
                @endif
            </x-ui.card>

            @if ($callback)
                <x-ui.card :title="__('callbacks.panel')" :description="__('callbacks.revealed_help')">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="font-mono text-sm text-fg">{{ $callbackPhone ?? $callback->maskedPhone() }}</p>
                            <p class="text-xs text-subtle">{{ $callback->reference }} · {{ $callback->language === 'pcm' ? 'Pidgin' : 'English' }}</p>
                        </div>
                        @if ($callbackPhone)
                            <x-ui.button size="sm" icon="phone" :href="'tel:'.$callbackPhone">{{ __('callbacks.call') }}</x-ui.button>
                        @elseif ($canWork)
                            <x-ui.button size="sm" variant="secondary" icon="eye" wire:click="revealCallback" loading="revealCallback">{{ __('callbacks.reveal') }}</x-ui.button>
                        @endif
                    </div>
                </x-ui.card>
            @endif

            <x-ui.card :title="__('tasks.evidence')" :description="__('tasks.evidence_help')" :padding="false">
                <ul class="divide-y divide-line">
                    @forelse ($evidenceFiles as $file)
                        <li class="flex items-center gap-3 px-5 py-3" wire:key="ev-{{ $file->id }}">
                            <x-icon :name="str_starts_with($file->mime, 'image/') ? 'eye' : 'file-text'" class="size-4 shrink-0 text-subtle" />
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('workspace.tasks.evidence', [$task, $file]) }}" class="block truncate text-sm font-medium text-fg hover:text-primary">{{ $file->original_name }}</a>
                                <p class="truncate text-xs text-subtle" title="SHA-256 {{ $file->sha256 }}">{{ $file->user->name }} · {{ \Illuminate\Support\Number::fileSize($file->size) }} · <span class="font-mono">{{ substr($file->sha256, 0, 12) }}…</span></p>
                            </div>
                        </li>
                    @empty
                        <li><x-ui.empty-state compact icon="paperclip" :title="__('tasks.no_evidence')" /></li>
                    @endforelse
                </ul>
                @if ($canWork)
                    <div class="border-t border-line px-5 py-4">
                        <label class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-dashed border-line-strong px-4 py-3 text-sm font-medium text-muted transition-colors hover:border-primary hover:text-primary">
                            <x-icon name="upload" class="size-4" />
                            <span wire:loading.remove wire:target="evidence">{{ __('tasks.upload') }}</span>
                            <span wire:loading wire:target="evidence">{{ __('ui.loading') }}</span>
                            <input type="file" wire:model="evidence" accept="image/jpeg,image/png,image/webp,application/pdf" class="sr-only">
                        </label>
                        @error('evidence') <p class="mt-2 text-[0.8125rem] text-danger">{{ $message }}</p> @enderror
                    </div>
                @endif
            </x-ui.card>
        </div>
    </div>

    <x-ui.modal name="complete-task" :title="__('tasks.complete')" :description="__('tasks.complete_help')" icon="check" tone="success">
        <form wire:submit="setStatus('completed')" id="complete-task-form">
            <x-ui.textarea wire:model="completionNote" :label="__('tasks.completion_note')" rows="3" optional />
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
            <x-ui.button type="submit" form="complete-task-form" loading="setStatus">{{ __('tasks.complete') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.modal name="escalate-task" :title="__('tasks.escalate')" :description="__('tasks.escalate_help')" icon="triangle-alert" tone="warning">
        <form wire:submit="escalate" id="escalate-task-form">
            <x-ui.textarea wire:model="escalationReason" :label="__('volunteers.reason')" rows="2" required />
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
            <x-ui.button type="submit" form="escalate-task-form" variant="danger" loading="escalate">{{ __('tasks.escalate') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    @if ($canManage)
        <x-ui.modal name="assign-task" :title="__('tasks.assign')" :description="__('tasks.assign_help')" icon="user-plus">
            <div class="space-y-3">
                <input type="search" wire:model.live.debounce.300ms="assigneeSearch" placeholder="{{ __('canvassing.search_people') }}" aria-label="{{ __('canvassing.search_people') }}" class="form-control">
                <div class="max-h-72 space-y-1 overflow-y-auto scroll-thin" role="radiogroup" aria-label="{{ __('tasks.assignee') }}">
                    @forelse ($candidates as $person)
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-line px-3 py-2 text-sm hover:border-line-strong has-[:checked]:border-primary has-[:checked]:bg-primary-soft/50" wire:key="p-{{ $person->id }}">
                            <input type="radio" wire:model="assigneeId" value="{{ $person->id }}" class="size-4 accent-[var(--c-primary)]">
                            <x-ui.avatar :name="$person->name" size="sm" />
                            <span class="flex-1 text-fg">{{ $person->name }}</span>
                            <span class="font-mono text-xs text-subtle">•••• {{ $person->phone_last4 }}</span>
                        </label>
                    @empty
                        <x-ui.empty-state compact icon="users" :title="__('canvassing.no_candidates')" :description="__('tasks.no_candidates_help')" />
                    @endforelse
                </div>
                @error('assigneeId') <p class="text-[0.8125rem] text-danger">{{ $message }}</p> @enderror
                @error('assignee') <p class="text-[0.8125rem] text-danger">{{ $message }}</p> @enderror
            </div>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button wire:click="assign" loading="assign">{{ __('tasks.assign') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>

        <x-ui.modal name="task-due" :title="__('tasks.change_due')" icon="calendar">
            <form wire:submit="saveDue" id="task-due-form">
                <x-ui.input type="datetime-local" wire:model="dueAt" :label="__('tasks.due_at')" optional />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="task-due-form" loading="saveDue">{{ __('ui.save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>

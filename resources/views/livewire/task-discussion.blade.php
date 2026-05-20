<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="rounded-lg border border-gray-200 p-4">
        <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Comments</h4>

        <div class="space-y-3 max-h-56 overflow-y-auto pr-1">
            @forelse($comments as $comment)
                <div class="rounded-lg bg-gray-50 px-3 py-2">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold text-gray-700">{{ $comment->user?->name ?? 'Deleted user' }}</p>
                        <p class="text-xs text-gray-400">{{ $comment->created_at->diffForHumans() }}</p>
                    </div>
                    <p class="mt-1 text-sm text-gray-700 whitespace-pre-line">{{ $comment->body }}</p>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-gray-400">No comments yet.</p>
            @endforelse
        </div>

        <form wire:submit="addComment" class="mt-3 space-y-2">
            <label for="task-comment-{{ $taskId }}" class="sr-only">Task comment</label>
            <textarea id="task-comment-{{ $taskId }}"
                      wire:model="comment"
                      rows="3"
                      placeholder="Write a comment..."
                      class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
            @error('comment') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
            <div class="flex justify-end">
                <button type="submit"
                        class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">
                    Add comment
                </button>
            </div>
        </form>
    </div>

    <div class="rounded-lg border border-gray-200 p-4">
        <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Activity</h4>
        <div class="space-y-3 max-h-72 overflow-y-auto pr-1">
            @forelse($activities as $activity)
                <div class="border-l-2 border-indigo-200 pl-3">
                    <p class="text-sm text-gray-700">{{ $activity->description }}</p>
                    <p class="mt-0.5 text-xs text-gray-400">
                        {{ $activity->user?->name ?? 'System' }} / {{ $activity->created_at->diffForHumans() }}
                    </p>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-gray-400">No activity yet.</p>
            @endforelse
        </div>
    </div>
</div>

<?php

namespace App\Http\Controllers\Announcements;

use App\Http\Controllers\Controller;
use App\Http\Requests\Announcements\StoreAnnouncementRequest;
use App\Http\Requests\Announcements\UpdateAnnouncementRequest;
use App\Models\Announcement;
use App\Models\Teacher;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use App\Services\AuditLogger;
use App\Services\ParentOverviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $query = Announcement::query()->with('createdBy')->latest();

        // Managers see all. Others see published messages visible to them,
        // plus anything they authored themselves (e.g. a parent re-reading
        // a message they sent to a teacher).
        if (! $user->hasAnyRole(['Super Administrator', 'School Administrator'])) {
            // Post-filter pagination would be messy with isVisibleTo's PHP
            // logic, so use collection pagination-like slice for
            // non-managers - acceptable because announcements is small.
            $all = $query->get()->filter(fn (Announcement $a) => $a->isVisibleTo($user)
                || (int) $a->created_by === (int) $user->id)->values();
            $announcements = $this->paginateCollection($all, 15);

            return view('announcements.index', compact('announcements'));
        }

        $announcements = $query->paginate(15)->withQueryString();

        return view('announcements.index', compact('announcements'));
    }

    public function create(): View
    {
        if ($this->isSenderOnly(auth()->user())) {
            return view('announcements.create', [
                'isSender' => true,
                'teachers' => ParentOverviewService::teacherOptions(auth()->user()),
            ]);
        }

        return view('announcements.create', [
            'roles' => $this->availableRoles(),
        ]);
    }

    public function store(StoreAnnouncementRequest $request): RedirectResponse
    {
        $user = $request->user();

        // A parent writes directly to their children's teachers: the
        // audience is forced server-side (any submitted target_roles are
        // ignored), the message goes out published, and only the selected
        // teachers are notified.
        if ($this->isSenderOnly($user)) {
            $teacherIds = array_map('intval', (array) $request->input('teacher_ids', []));

            $announcement = Announcement::create([
                'title' => $request->string('title'),
                'body' => $request->string('body'),
                'target_roles' => ['Teacher'],
                'target_user_ids' => $teacherIds,
                'created_by' => $user->id,
                'is_published' => true,
                'published_at' => now(),
            ]);

            AuditLogger::log('send', 'announcements', $announcement->id, null, $announcement->only(['title', 'target_user_ids']));

            $this->notifyTeachers($announcement, $teacherIds);

            return redirect()->route('announcements.show', $announcement)->with('status', __('app.announcement_sent_to_teacher'));
        }

        $announcement = Announcement::create([
            'title' => $request->string('title'),
            'body' => $request->string('body'),
            'target_roles' => $request->input('target_roles'),
            'created_by' => $user->id,
            'is_published' => false,
        ]);

        AuditLogger::log('create', 'announcements', $announcement->id, null, $announcement->only(['title', 'target_roles']));

        return redirect()->route('announcements.show', $announcement)->with('status', __('app.announcement_created'));
    }

    public function show(Announcement $announcement): View
    {
        abort_unless($announcement->isVisibleTo(auth()->user()) || auth()->user()->can('announcements.manage'), 403);

        return view('announcements.show', compact('announcement'));
    }

    public function edit(Announcement $announcement): View
    {
        $this->assertOwnerOrManager($announcement);

        if ($this->isSenderOnly(auth()->user())) {
            return view('announcements.edit', [
                'announcement' => $announcement,
                'isSender' => true,
                'teachers' => ParentOverviewService::teacherOptions(auth()->user()),
            ]);
        }

        return view('announcements.edit', [
            'announcement' => $announcement,
            'roles' => $this->availableRoles(),
        ]);
    }

    public function update(UpdateAnnouncementRequest $request, Announcement $announcement): RedirectResponse
    {
        $this->assertOwnerOrManager($announcement);

        // Senders can re-address their own message among their children's
        // teachers; the Teacher-role audience itself stays forced.
        if ($this->isSenderOnly($request->user())) {
            $old = $announcement->only(['title', 'body', 'target_user_ids']);
            $teacherIds = array_map('intval', (array) $request->input('teacher_ids', []));

            $announcement->update([
                'title' => $request->string('title'),
                'body' => $request->string('body'),
                'target_user_ids' => $teacherIds,
            ]);

            AuditLogger::log('update', 'announcements', $announcement->id, $old, $announcement->only(['title', 'body', 'target_user_ids']));

            return redirect()->route('announcements.show', $announcement)->with('status', __('app.announcement_updated'));
        }

        $old = $announcement->only(['title', 'body', 'target_roles']);

        $announcement->update([
            'title' => $request->string('title'),
            'body' => $request->string('body'),
            'target_roles' => $request->input('target_roles'),
        ]);

        AuditLogger::log('update', 'announcements', $announcement->id, $old, $announcement->only(['title', 'body', 'target_roles']));

        return redirect()->route('announcements.show', $announcement)->with('status', __('app.announcement_updated'));
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $this->assertOwnerOrManager($announcement);

        $announcement->delete();

        AuditLogger::log('delete', 'announcements', $announcement->id);

        return redirect()->route('announcements.index')->with('status', __('app.announcement_deleted'));
    }

    public function togglePublish(Announcement $announcement): RedirectResponse
    {
        $wasPublished = $announcement->is_published;

        $announcement->update([
            'is_published' => ! $wasPublished,
            'published_at' => ! $wasPublished ? now() : null,
        ]);

        AuditLogger::log($wasPublished ? 'unpublish' : 'publish', 'announcements', $announcement->id);

        if (! $wasPublished) {
            $this->notifyAudience($announcement);
        }

        $message = $wasPublished ? __('app.announcement_unpublished') : __('app.announcement_published');

        return back()->with('status', $message);
    }

    /**
     * Fan out into notifications table for the selected roles.
     * Empty target_roles = broadcast to every active user.
     */
    private function notifyAudience(Announcement $announcement): void
    {
        $targets = $announcement->target_roles;

        $query = User::query()->where('is_active', true);

        if (! empty($targets)) {
            $query->whereHas('roles', fn ($q) => $q->whereIn('name', $targets));
        }

        $recipients = $query->get();

        Notification::send($recipients, new AnnouncementNotification($announcement));
    }

    /**
     * A "sender" (currently: a parent) may author messages to teachers but
     * never manage the module - managers keep the full authoring flow.
     */
    private function isSenderOnly(User $user): bool
    {
        return $user->can('announcements.send') && ! $user->can('announcements.manage');
    }

    /**
     * Senders may only touch their own messages; managers may touch any.
     */
    private function assertOwnerOrManager(Announcement $announcement): void
    {
        $user = auth()->user();

        if ($user->can('announcements.manage')) {
            return;
        }

        abort_unless(
            $user->can('announcements.send') && (int) $announcement->created_by === (int) $user->id,
            403
        );
    }

    /**
     * Notify the linked user accounts of the addressed teachers only -
     * not every teacher in the school, and nobody else.
     */
    private function notifyTeachers(Announcement $announcement, array $teacherIds): void
    {
        $userIds = Teacher::query()
            ->whereIn('id', $teacherIds)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->all();

        if ($userIds === []) {
            return;
        }

        $recipients = User::query()->whereIn('id', $userIds)->where('is_active', true)->get();

        Notification::send($recipients, new AnnouncementNotification($announcement));
    }

    private function paginateCollection($collection, int $perPage = 15)
    {
        $page = request()->integer('page', 1);
        $total = $collection->count();
        $items = $collection->slice(($page - 1) * $perPage, $perPage)->values();

        return new \Illuminate\Pagination\LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }

    private function availableRoles(): array
    {
        return ['Teacher', 'Parent', 'Student', 'Librarian', 'Storekeeper', 'Receptionist', 'Accountant', 'Principal'];
    }
}

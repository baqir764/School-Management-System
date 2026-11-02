import * as bootstrapUi from 'bootstrap';

window.bootstrap = bootstrapUi;

/*
 * Chart.js is deliberately NOT imported here: it is ~200 KB and was
 * previously shipped inside this bundle, i.e. downloaded and parsed on
 * EVERY page of the app, while only the dashboard ever draws a chart.
 * It now lives in its own entry (resources/js/dashboard.js), which the
 * dashboard page loads on its own and which lazy-imports Chart.js only
 * when a chart canvas is actually present - see vite.config.js inputs.
 *
 * axios (resources/js/bootstrap.js) was removed the same way: nothing in
 * the app ever called it, so every page paid for it for nothing.
 */

/*
 * DataTables (jQuery-based) is intentionally NOT wired up yet - no Phase 1
 * table needs it, and its side-effect imports were firing on every page
 * load regardless, which is what broke the dashboard's chart rendering.
 * Phase 2's larger student/teacher rosters are the right place to bring it
 * back in, with a table on the page to actually attach it to.
 */

/*
 * Auto-initialize every Bootstrap tooltip/popover on the page and show
 * toast notifications - small conveniences used across every module's
 * Blade views.
 */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));

    document.querySelectorAll('.toast').forEach((el) => {
        const toast = bootstrap.Toast.getOrCreateInstance(el);
        toast.show();
    });

    /*
     * Generic delete/disable confirmation: any form with
     * data-confirm="message" pops a native confirm() before submitting.
     * Kept dependency-free on purpose - every module's destructive action
     * button reuses this instead of hand-rolling its own dialog.
     */
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });

    /*
     * Mobile off-canvas sidebar. On <lg breakpoints the sidebar is
     * position:fixed and translated off-screen (see shared.scss). The
     * toggle button, a backdrop tap, the Escape key, or tapping a nav
     * link all open/close it. While open we lock body scroll (so the
     * page behind doesn't scroll) and make the sidebar itself
     * scrollable - otherwise on a short viewport the lower nav items
     * sit below the screen with no way to reach them.
     */
    const sidebar = document.getElementById('smsSidebar');
    const backdrop = document.getElementById('smsSidebarBackdrop');
    const toggleBtn = document.getElementById('smsSidebarToggle');
    const mq = window.matchMedia('(max-width: 991.98px)');

    function isMobile() {
        return mq.matches;
    }

    function openSidebar() {
        if (!sidebar || !isMobile()) return;
        sidebar.classList.add('show');
        if (backdrop) {
            backdrop.hidden = false;
            // Next frame so the transition from display:none actually runs.
            requestAnimationFrame(() => backdrop.classList.add('show'));
        }
        document.body.classList.add('sms-sidebar-open');
        if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'true');
    }

    function closeSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove('show');
        if (backdrop) {
            backdrop.classList.remove('show');
            // Hide after the fade-out transition finishes so it doesn't
            // intercept clicks while closed.
            setTimeout(() => { if (!sidebar.classList.contains('show')) backdrop.hidden = true; }, 220);
        }
        document.body.classList.remove('sms-sidebar-open');
        if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'false');
    }

    function toggleSidebar() {
        if (sidebar && sidebar.classList.contains('show')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }

    if (toggleBtn) toggleBtn.addEventListener('click', toggleSidebar);
    if (backdrop) backdrop.addEventListener('click', closeSidebar);

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && sidebar && sidebar.classList.contains('show')) {
            closeSidebar();
        }
    });

    // Tapping a nav link on mobile should close the drawer after
    // navigation, so the new page doesn't re-open covered by a sidebar.
    if (sidebar) {
        sidebar.querySelectorAll('.nav-link').forEach((link) => {
            link.addEventListener('click', () => {
                if (isMobile()) closeSidebar();
            });
        });
    }

    // If the user resizes up past the breakpoint, make sure the
    // open-state/backdrop/scroll-lock don't linger on desktop where the
    // sidebar is inline in the flow again.
    mq.addEventListener('change', (e) => {
        if (!e.matches) closeSidebar();
    });
});

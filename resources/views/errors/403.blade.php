{{--
    abort_if()/abort() calls across the app (e.g. "cannot delete a class
    that still has sections", "cannot delete a teacher assigned to
    sections") pass a specific, translated reason as the exception message.
    Laravel hands that exception to every custom error view as $exception,
    so show it when present instead of always falling back to the generic
    "access denied" text - that generic message was swallowing every one
    of those specific reasons, leaving no way to tell a real permission
    problem apart from an "in use" business rule.
--}}
<x-layouts.error
    code="403"
    :heading="__('app.error_403_heading')"
    :message="($exception ?? null)?->getMessage() ?: __('app.error_403_message')"
/>

<?php

return [
    /*
     | Force-inline the design-system CSS/JS even when compiled assets exist
     | (`php sofy ui:build`). Handy in development when you're editing component
     | styles and don't want to rebuild each time. Production should leave this
     | false so the cached static assets are served.
     */
    'inline' => (bool) env('UI_INLINE', false),
];

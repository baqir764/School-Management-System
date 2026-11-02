
## One login for both sides (v1.8): token-to-session bridge

The offline section logs in with a Sanctum token, but the Live tab is a
cookie session - two separate auth states. Since v1.8 the app links them:
tapping Live calls `POST /api/session` (Bearer token), which returns a
single-use 2-minute URL (`/auth/token/{nonce}`); the app navigates THERE
and lands on the dashboard already signed in. No second login screen.
Fallback (no token / minting failed) is the plain site root.

## Single-shot camera (v1.8)

The camera stops after EVERY successful decode - one scan can never
produce more than one row, whatever the card holds. Tap Start Camera
again for the next card. (The 3-second same-code guard is still there as
a second net, but single-shot is what guarantees it.)

# Notes on the legacy files

- The two `SS_Pol_Portal.html` copies differ by 16 lines. The one in `PL Project` is the newer: it adds a warning (and an origin check) when the page is not opened from the SharePoint site. The `PP Project` copy has demo sign-in hints in its login text. Kept: the `PL Project` version as the reference for the Policy Portal flow.
- The `PP Project` copy shows demo sign-in hints in its login text. None of the old browser-side sign-in is reused; real sign-in is server-side (see `authentication/README.md`).

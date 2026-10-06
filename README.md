# Nubia KC Estates Ltd website

- `index.html`: the full site in one file. Upload it to the repo root, then enable GitHub Pages (Settings → Pages → Deploy from branch → main / root).
- `source/`: editable design source (`Nubia Website.dc.html`, `support.js`, `styles.css`, `tokens/`, `assets/`). Open the .dc.html in a browser to preview.

Before going live
- Forms: replace `https://formspree.io/f/[FORM_ID]` in the source with your Formspree endpoint, then rebuild `index.html`. Until then, forms only simulate sending.
- Placeholders in [brackets] (fee, team, founder, testimonials, address, hours, blog excerpts) still need real content.
- Admin (footer → Staff login) saves changes only in the visitor's browser; a live admin needs a backend or CMS.

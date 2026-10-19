# React table design

User-approved direction: neutral styled component, responsive, themed with CSS variables, no UI framework dependency.

Use the application's system font. Light surfaces are white on a pale neutral canvas with charcoal text; dark surfaces use slate neutrals. A restrained blue accent marks focus, links and selection. Dense tables use 14px text, 44px minimum interactive targets where practical, subtle dividers, rounded outer cards and clear type hierarchy. Numeric cells align right. Status uses text alongside color.

The reusable Table fits inside application layouts. TablePage adds a title, description, header action slot and comfortable page margins. Mobile tables scroll inside a labeled region while toolbar controls wrap. Keep content and actions reachable at 320px.

Use native table semantics, captions, labeled controls, aria-sort, checkbox mixed state, polite live updates, alert errors, and a native modal confirmation dialog with cancel focus and focus restoration. All content is escaped; custom renderers own their supplied React nodes.

CSS is scoped to tb-* classes and variables prefixed --tb-. Support system color scheme and explicit data-theme light/dark. No global reset, fonts or CDN. Honor reduced motion. Provide renderCell/renderIcon hooks and CSS overrides for integration with host design systems.

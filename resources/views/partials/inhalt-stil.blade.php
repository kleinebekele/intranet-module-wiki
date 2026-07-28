{{--
    Darstellung des gerenderten Markdown-Textes.

    Bewusst als eigenes Stylesheet statt ueber @tailwindcss/typography: Das
    Plugin steckt nicht im Core, und ein Modul soll dem Core keine npm-
    Abhaengigkeit aufzwingen. Tailwinds Preflight setzt Ueberschriften und
    Listen zurueck - ohne diese paar Zeilen saehe ein Wiki-Text aus wie
    Fliesstext ohne Gliederung.
--}}
<style>
    .wiki-inhalt > *:first-child { margin-top: 0; }
    .wiki-inhalt > *:last-child { margin-bottom: 0; }
    .wiki-inhalt p { margin: 0.75rem 0; line-height: 1.65; }
    .wiki-inhalt h1,
    .wiki-inhalt h2,
    .wiki-inhalt h3,
    .wiki-inhalt h4 { margin: 1.25rem 0 0.5rem; font-weight: 600; color: #1f2937; line-height: 1.3; }
    .wiki-inhalt h1 { font-size: 1.375rem; }
    .wiki-inhalt h2 { font-size: 1.175rem; }
    .wiki-inhalt h3 { font-size: 1.05rem; }
    .wiki-inhalt h4 { font-size: 0.95rem; }
    .wiki-inhalt ul,
    .wiki-inhalt ol { margin: 0.75rem 0; padding-left: 1.5rem; }
    .wiki-inhalt ul { list-style: disc; }
    .wiki-inhalt ol { list-style: decimal; }
    .wiki-inhalt li { margin: 0.25rem 0; }
    .wiki-inhalt li > ul,
    .wiki-inhalt li > ol { margin: 0.25rem 0; }
    .wiki-inhalt a { color: #4338ca; text-decoration: underline; }
    .wiki-inhalt a:hover { color: #3730a3; }
    .wiki-inhalt strong { font-weight: 600; color: #111827; }
    .wiki-inhalt em { font-style: italic; }
    .wiki-inhalt code {
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-size: 0.875em;
        background: #f3f4f6;
        border-radius: 0.25rem;
        padding: 0.1rem 0.3rem;
    }
    .wiki-inhalt pre {
        margin: 0.75rem 0;
        padding: 0.75rem 1rem;
        background: #1f2937;
        color: #f9fafb;
        border-radius: 0.5rem;
        overflow-x: auto;
        font-size: 0.85rem;
        line-height: 1.5;
    }
    .wiki-inhalt pre code { background: transparent; padding: 0; color: inherit; }
    .wiki-inhalt blockquote {
        margin: 0.75rem 0;
        padding: 0.25rem 0 0.25rem 1rem;
        border-left: 3px solid #c7d2fe;
        color: #4b5563;
    }
    .wiki-inhalt hr { margin: 1.25rem 0; border-color: #e5e7eb; }
    .wiki-inhalt table { width: 100%; margin: 0.75rem 0; font-size: 0.875rem; border-collapse: collapse; display: block; overflow-x: auto; }
    .wiki-inhalt th,
    .wiki-inhalt td { border: 1px solid #e5e7eb; padding: 0.375rem 0.625rem; text-align: left; }
    .wiki-inhalt th { background: #f9fafb; font-weight: 600; }
    .wiki-inhalt img { max-width: 100%; height: auto; }
</style>

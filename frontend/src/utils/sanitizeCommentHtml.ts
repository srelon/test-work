const ALLOWED_TAGS = new Set(['a', 'code', 'i', 'strong'])

const BLOCK_TAGS = new Set(['p', 'br', 'div'])

const ALLOWED_ATTRS: Record<string, string[]> = {
    a: ['href', 'title'],
}

const ALLOWED_HREF_PROTOCOLS = new Set(['http:', 'https:', 'mailto:'])

function is_allowed_href(value: string): boolean {
    try {
        return ALLOWED_HREF_PROTOCOLS.has(new URL(value, window.location.href).protocol)
    } catch {
        return false
    }
}

function sanitize_children(parent: Node) {
    for (const node of Array.from(parent.childNodes)) {
        if (node.nodeType === Node.TEXT_NODE) continue

        if (node.nodeType !== Node.ELEMENT_NODE) {
            node.parentNode?.removeChild(node)
            continue
        }

        const element = node as HTMLElement
        sanitize_children(element)

        const tag = element.tagName.toLowerCase()
        if (!ALLOWED_TAGS.has(tag)) {
            while (element.firstChild) parent.insertBefore(element.firstChild, element)
            if (BLOCK_TAGS.has(tag)) parent.insertBefore(document.createTextNode('\n'), element)
            parent.removeChild(element)
            continue
        }

        const allowed_attrs = ALLOWED_ATTRS[tag] ?? []
        for (const attr of Array.from(element.attributes)) {
            if (!allowed_attrs.includes(attr.name)) element.removeAttribute(attr.name)
        }

        if (tag === 'a') {
            const href = element.getAttribute('href')
            if (href && !is_allowed_href(href)) element.removeAttribute('href')
            element.setAttribute('rel', 'nofollow noindex noopener noreferrer')
            element.setAttribute('target', '_blank')
        }
    }
}

// Whitelists a, code, i, strong (the same set the comment editor's TipTap
// extensions are configured to produce) and strips every other tag/attribute,
// so display never trusts raw stored HTML.
export function sanitize_comment_html(html: string): string {
    const template = document.createElement('template')
    template.innerHTML = html
    sanitize_children(template.content)
    return template.innerHTML.trim()
}

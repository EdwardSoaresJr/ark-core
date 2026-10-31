import selectorParser from 'postcss-selector-parser';

// Unmigrated components on Tabler pages sit inside .ark-legacy and are styled by app.css alone.
// Appending :not(.ark-legacy *) to the subject of every rule in the Tabler bundle keeps Tabler out of them.

const legacyPseudoElements = new Set([':before', ':after', ':first-line', ':first-letter']);

function isPseudoElement(node) {
    return node.type === 'pseudo' && (node.value.startsWith('::') || legacyPseudoElements.has(node.value));
}

function outsideLegacyIslands() {
    return selectorParser.pseudo({
        value: ':not',
        nodes: [
            selectorParser.selector({
                nodes: [
                    selectorParser.className({ value: 'ark-legacy' }),
                    selectorParser.combinator({ value: ' ' }),
                    selectorParser.universal(),
                ],
            }),
        ],
    });
}

const scopeSelector = selectorParser((selectors) => {
    selectors.each((selector) => {
        // Whitespace inside a selector is a descendant combinator, so the list's whitespace stays outside the scope.
        const leading = selector.first.spaces.before;
        const trailing = selector.last.spaces.after;
        selector.first.spaces.before = '';
        selector.last.spaces.after = '';
        let pseudoElement = null;

        for (let index = selector.nodes.length - 1; index >= 0; index--) {
            const node = selector.nodes[index];

            if (node.type === 'combinator') {
                break;
            }

            if (isPseudoElement(node)) {
                pseudoElement = node;
            }
        }

        if (pseudoElement) {
            selector.insertBefore(pseudoElement, outsideLegacyIslands());
        } else {
            selector.append(outsideLegacyIslands());
        }

        selector.first.spaces.before = leading;
        selector.last.spaces.after = trailing;
    });
});

export default function excludeLegacyIslands({ entry }) {
    return {
        postcssPlugin: 'ark-exclude-legacy-islands',
        Once(root) {
            const file = (root.source?.input?.file ?? '').replaceAll('\\', '/');

            if (! file.endsWith(entry)) {
                return;
            }

            root.walkRules((rule) => {
                if (rule.parent?.type === 'atrule' && /keyframes$/i.test(rule.parent.name)) {
                    return;
                }

                rule.selector = scopeSelector.processSync(rule.selector);
            });
        },
    };
}

excludeLegacyIslands.postcss = true;

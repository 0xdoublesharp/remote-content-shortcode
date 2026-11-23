(function (wp, metadata) {
    'use strict';
    var el = wp.element.createElement;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var useRef = wp.element.useRef;
    var __ = wp.i18n.__;
    var c = wp.components;
    var ServerSideRender = wp.serverSideRender.ServerSideRender || wp.serverSideRender.default || wp.serverSideRender;
    var name = 'remote-content-shortcode/remote-content';
    var cacheAttributes = ['cache', 'cacheTtl', 'staleTtl'];
    var aliases = Array.isArray(metadata.aliases) ? metadata.aliases : [];

    function isPreviewUrl(value) {
        var url = typeof value === 'string' ? value.trim() : '';
        if (!/^https?:\/\/[^/\s]+/i.test(url)) {
            return false;
        }
        try {
            var parsed = new URL(url);
            // A public source needs a complete hostname, including when the author is still typing it.
            return !parsed.username && !parsed.password && /^[^.]+(?:\.[^.]+)+\.?$/.test(parsed.hostname);
        } catch (error) {
            return false;
        }
    }

    function previewSignature(attributes) {
        return JSON.stringify(Object.keys(attributes).sort().filter(function (key) {
            return cacheAttributes.indexOf(key) === -1;
        }).map(function (key) {
            return [key, attributes[key]];
        }));
    }

    function DraftNumberControl(props) {
        var value = props.value == null ? '' : String(props.value);
        var draftState = useState(value);
        var draft = draftState[0];
        var setDraft = draftState[1];

        useEffect(function () {
            setDraft(value);
        }, [value]);

        function commit() {
            if (!/^-?\d+$/.test(draft)) {
                setDraft(value);
                return;
            }
            var number = Math.max(props.min, Math.min(props.max, Number(draft)));
            setDraft(String(number));
            if (number !== props.value) {
                props.onCommit(number);
            }
        }

        return el(c.TextControl, {
            label: props.label,
            type: 'number',
            min: props.min,
            max: props.max,
            step: 1,
            value: draft,
            onChange: setDraft,
            onBlur: commit
        });
    }

    function EmptyResponsePlaceholder() {
        var message = metadata.toolsUrl
            ? wp.element.createInterpolateElement(
                __('No content was returned for this preview. Check the URL, selector, or test it in <tools>Tools → Remote content</tools>.', 'remote-content-shortcode'),
                { tools: el('a', { href: metadata.toolsUrl }) }
            )
            : __('No content was returned for this preview. Check the source URL and CSS selector.', 'remote-content-shortcode');
        return el(c.Placeholder, { label: __('Remote content', 'remote-content-shortcode') }, message);
    }

    wp.blocks.registerBlockType(name, {
        edit: function (props) {
            var a = props.attributes;
            var canPreview = isPreviewUrl(a.url);
            var signature = canPreview ? previewSignature(a) : null;
            var latestAttributes = useRef(a);
            var previewState = useState(function () {
                return { attributes: a, signature: signature, revision: 0 };
            });
            var preview = previewState[0];
            var setPreview = previewState[1];

            useEffect(function () {
                latestAttributes.current = a;
            }, [a]);

            useEffect(function () {
                if (signature === preview.signature) {
                    return;
                }
                if (signature === null) {
                    setPreview(function (previous) {
                        return { attributes: null, signature: null, revision: previous.revision };
                    });
                    return;
                }
                var timer = setTimeout(function () {
                    setPreview(function (previous) {
                        return { attributes: latestAttributes.current, signature: signature, revision: previous.revision };
                    });
                }, 800);
                return function () { clearTimeout(timer); };
            }, [signature, preview.signature]);

            function refreshPreview() {
                setPreview(function (previous) {
                    return { attributes: a, signature: signature, revision: previous.revision + 1 };
                });
            }

            function setAttribute(key) {
                return function (value) {
                    var next = {};
                    next[key] = value;
                    props.setAttributes(next);
                };
            }

            function text(key, label, extra) {
                return el(c.TextControl, Object.assign({ label: label, value: a[key], onChange: setAttribute(key) }, extra));
            }

            function number(key, label, min, max) {
                return el(DraftNumberControl, { key: key, label: label, value: a[key], min: min, max: max, onCommit: setAttribute(key) });
            }

            function toggle(key, label) {
                return el(c.ToggleControl, { label: label, checked: a[key], onChange: setAttribute(key) });
            }

            function setAlias(value) {
                if (/^[A-Za-z0-9_.-]{0,64}$/.test(value)) {
                    setAttribute('userpwd')(value);
                }
            }

            return el('div', wp.blockEditor.useBlockProps(),
                el(wp.blockEditor.InspectorControls, null,
                    el(c.PanelBody, { title: __('Source & selection', 'remote-content-shortcode'), initialOpen: true },
                        text('url', __('Source URL', 'remote-content-shortcode'), { type: 'url' }),
                        text('selector', __('CSS selector', 'remote-content-shortcode')),
                        text('remove', __('Remove selector', 'remote-content-shortcode')),
                        aliases.length ? el(c.SelectControl, {
                            label: __('Configured aliases', 'remote-content-shortcode'),
                            value: aliases.indexOf(a.userpwd) === -1 ? '' : a.userpwd,
                            options: [{ label: __('Select an alias', 'remote-content-shortcode'), value: '' }].concat(aliases.map(function (alias) {
                                return { label: alias, value: alias };
                            })),
                            onChange: setAlias
                        }) : null,
                        text('userpwd', __('Credential alias', 'remote-content-shortcode'), {
                            help: __('Use a configured alias. Never enter a username or password.', 'remote-content-shortcode'),
                            maxLength: 64,
                            onChange: setAlias
                        }),
                        el(c.Button, { variant: 'secondary', disabled: !canPreview, onClick: refreshPreview },
                            __('Refresh preview', 'remote-content-shortcode'))),
                    el(c.PanelBody, { title: __('Content & transforms', 'remote-content-shortcode'), initialOpen: false },
                        text('params', __('Forward query parameters', 'remote-content-shortcode')),
                        text('find', __('Find regex', 'remote-content-shortcode')),
                        text('replace', __('Replacement', 'remote-content-shortcode')),
                        el(c.TextareaControl, { label: __('Fallback HTML', 'remote-content-shortcode'), value: a.fallback, onChange: setAttribute('fallback') }),
                        toggle('rewriteUrls', __('Rewrite relative URLs', 'remote-content-shortcode')),
                        toggle('stripTags', __('Strip HTML tags', 'remote-content-shortcode')),
                        toggle('htmlentities', __('Encode HTML entities', 'remote-content-shortcode'))),
                    el(c.PanelBody, { title: __('Caching & request', 'remote-content-shortcode'), initialOpen: false },
                        toggle('cache', __('Cache response', 'remote-content-shortcode')),
                        number('cacheTtl', __('Cache lifetime (seconds)', 'remote-content-shortcode'), 1, 86400),
                        number('staleTtl', __('Stale lifetime (seconds)', 'remote-content-shortcode'), 0, 86400),
                        number('timeout', __('Timeout (seconds)', 'remote-content-shortcode'), 1, 30)),
                    metadata.showProHint && metadata.proUrl ? el(c.PanelBody, null,
                        el('p', { className: 'components-base-control__help' },
                            __('Need reusable saved sources, JSON templates, or scheduled refreshes?', 'remote-content-shortcode'), ' ',
                            el('a', { href: metadata.proUrl }, __('Learn about Pro.', 'remote-content-shortcode')))) : null),
                canPreview && preview.signature !== null
                    ? el(ServerSideRender, {
                        key: preview.revision,
                        block: name,
                        attributes: preview.attributes,
                        httpMethod: 'POST',
                        EmptyResponsePlaceholder: EmptyResponsePlaceholder
                    })
                    : el(c.Placeholder, { label: __('Remote content', 'remote-content-shortcode') },
                        __('Set a complete HTTP or HTTPS source URL in the block settings.', 'remote-content-shortcode')));
        },
        save: function () { return null; }
    });
}(window.wp, window.rcsBlockEditor || {}));

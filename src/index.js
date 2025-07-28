(function () {
  const { registerBlockType } = wp.blocks;
  const { createElement, Fragment } = wp.element;
  const { InspectorControls, useBlockProps } = wp.blockEditor;
  const { PanelBody, SelectControl, ToggleControl, RangeControl, TextControl, ColorPicker } =
    wp.components;
  const { __ } = wp.i18n;
  const { select } = wp.data;

  // Prompt Display Block
  registerBlockType('prompt-manager/prompt-display', {
    title: __('Prompt Display', 'prompt-manager'),
    icon: 'lightbulb',
    category: 'prompt-manager',
    attributes: {
      promptId: {
        type: 'number',
        default: 0,
      },
      showTitle: {
        type: 'boolean',
        default: true,
      },
      showExcerpt: {
        type: 'boolean',
        default: true,
      },
      showImage: {
        type: 'boolean',
        default: true,
      },
      imageSize: {
        type: 'string',
        default: 'medium',
      },
      alignment: {
        type: 'string',
        default: 'none',
      },
    },
    edit: function (props) {
      const { attributes, setAttributes } = props;
      const blockProps = useBlockProps();

      return createElement(
        Fragment,
        {},
        createElement(
          'div',
          blockProps,
          createElement(
            'div',
            { className: 'prompt-manager-block-placeholder' },
            attributes.promptId
              ? createElement(
                  'p',
                  {},
                  __('Prompt Display: ', 'prompt-manager') +
                    (promptManagerBlocks.prompts.find((p) => p.value === attributes.promptId)
                      ?.label || __('Unknown Prompt', 'prompt-manager'))
                )
              : createElement('p', {}, __('Select a prompt to display', 'prompt-manager'))
          )
        ),
        createElement(
          InspectorControls,
          {},
          createElement(
            PanelBody,
            { title: __('Prompt Settings', 'prompt-manager') },
            createElement(SelectControl, {
              label: __('Select Prompt', 'prompt-manager'),
              value: attributes.promptId,
              options: [{ value: 0, label: __('Select a prompt...', 'prompt-manager') }].concat(
                promptManagerBlocks.prompts
              ),
              onChange: function (value) {
                setAttributes({ promptId: parseInt(value) });
              },
            }),
            createElement(ToggleControl, {
              label: __('Show Title', 'prompt-manager'),
              checked: attributes.showTitle,
              onChange: function (value) {
                setAttributes({ showTitle: value });
              },
            }),
            createElement(ToggleControl, {
              label: __('Show Excerpt', 'prompt-manager'),
              checked: attributes.showExcerpt,
              onChange: function (value) {
                setAttributes({ showExcerpt: value });
              },
            }),
            createElement(ToggleControl, {
              label: __('Show Image', 'prompt-manager'),
              checked: attributes.showImage,
              onChange: function (value) {
                setAttributes({ showImage: value });
              },
            }),
            attributes.showImage &&
              createElement(SelectControl, {
                label: __('Image Size', 'prompt-manager'),
                value: attributes.imageSize,
                options: promptManagerBlocks.imageSizes,
                onChange: function (value) {
                  setAttributes({ imageSize: value });
                },
              }),
            createElement(SelectControl, {
              label: __('Alignment', 'prompt-manager'),
              value: attributes.alignment,
              options: [
                { value: 'none', label: __('None', 'prompt-manager') },
                { value: 'left', label: __('Left', 'prompt-manager') },
                { value: 'center', label: __('Center', 'prompt-manager') },
                { value: 'right', label: __('Right', 'prompt-manager') },
              ],
              onChange: function (value) {
                setAttributes({ alignment: value });
              },
            })
          )
        )
      );
    },
    save: function () {
      return null; // Rendered by PHP
    },
  });

  // Prompt Gallery Block
  registerBlockType('prompt-manager/prompt-gallery', {
    title: __('Prompt Gallery', 'prompt-manager'),
    icon: 'grid-view',
    category: 'prompt-manager',
    attributes: {
      numberOfPosts: {
        type: 'number',
        default: 6,
      },
      columns: {
        type: 'number',
        default: 3,
      },
      showNSFW: {
        type: 'boolean',
        default: false,
      },
      orderBy: {
        type: 'string',
        default: 'date',
      },
      order: {
        type: 'string',
        default: 'DESC',
      },
      category: {
        type: 'string',
        default: '',
      },
    },
    edit: function (props) {
      const { attributes, setAttributes } = props;
      const blockProps = useBlockProps();

      return createElement(
        Fragment,
        {},
        createElement(
          'div',
          blockProps,
          createElement(
            'div',
            { className: 'prompt-manager-block-placeholder' },
            createElement('p', {}, __('Prompt Gallery', 'prompt-manager')),
            createElement(
              'small',
              {},
              __('Showing', 'prompt-manager') +
                ' ' +
                attributes.numberOfPosts +
                ' ' +
                __('prompts in', 'prompt-manager') +
                ' ' +
                attributes.columns +
                ' ' +
                __('columns', 'prompt-manager')
            )
          )
        ),
        createElement(
          InspectorControls,
          {},
          createElement(
            PanelBody,
            { title: __('Gallery Settings', 'prompt-manager') },
            createElement(RangeControl, {
              label: __('Number of Posts', 'prompt-manager'),
              value: attributes.numberOfPosts,
              onChange: function (value) {
                setAttributes({ numberOfPosts: value });
              },
              min: 1,
              max: 20,
            }),
            createElement(RangeControl, {
              label: __('Columns', 'prompt-manager'),
              value: attributes.columns,
              onChange: function (value) {
                setAttributes({ columns: value });
              },
              min: 1,
              max: 6,
            }),
            createElement(ToggleControl, {
              label: __('Show NSFW Content', 'prompt-manager'),
              checked: attributes.showNSFW,
              onChange: function (value) {
                setAttributes({ showNSFW: value });
              },
            }),
            createElement(SelectControl, {
              label: __('Order By', 'prompt-manager'),
              value: attributes.orderBy,
              options: [
                { value: 'date', label: __('Date', 'prompt-manager') },
                { value: 'title', label: __('Title', 'prompt-manager') },
                { value: 'menu_order', label: __('Menu Order', 'prompt-manager') },
                { value: 'rand', label: __('Random', 'prompt-manager') },
              ],
              onChange: function (value) {
                setAttributes({ orderBy: value });
              },
            }),
            createElement(SelectControl, {
              label: __('Order', 'prompt-manager'),
              value: attributes.order,
              options: [
                { value: 'DESC', label: __('Descending', 'prompt-manager') },
                { value: 'ASC', label: __('Ascending', 'prompt-manager') },
              ],
              onChange: function (value) {
                setAttributes({ order: value });
              },
            })
          )
        )
      );
    },
    save: function () {
      return null; // Rendered by PHP
    },
  });

  // NSFW Warning Block
  registerBlockType('prompt-manager/nsfw-warning', {
    title: __('NSFW Warning', 'prompt-manager'),
    icon: 'warning',
    category: 'prompt-manager',
    attributes: {
      warningText: {
        type: 'string',
        default: __(
          'This content contains NSFW material. You must be logged in to view it.',
          'prompt-manager'
        ),
      },
      buttonText: {
        type: 'string',
        default: __('Login to View', 'prompt-manager'),
      },
      backgroundColor: {
        type: 'string',
        default: '#fef2f2',
      },
      textColor: {
        type: 'string',
        default: '#dc2626',
      },
    },
    edit: function (props) {
      const { attributes, setAttributes } = props;
      const blockProps = useBlockProps({
        style: {
          backgroundColor: attributes.backgroundColor,
          color: attributes.textColor,
          padding: '20px',
          borderRadius: '8px',
          textAlign: 'center',
        },
      });

      return createElement(
        Fragment,
        {},
        createElement(
          'div',
          blockProps,
          createElement('p', {}, attributes.warningText),
          createElement(
            'button',
            {
              className: 'button',
              style: {
                backgroundColor: attributes.textColor,
                color: attributes.backgroundColor,
                border: 'none',
                padding: '10px 20px',
                borderRadius: '4px',
              },
            },
            attributes.buttonText
          )
        ),
        createElement(
          InspectorControls,
          {},
          createElement(
            PanelBody,
            { title: __('Warning Settings', 'prompt-manager') },
            createElement(TextControl, {
              label: __('Warning Text', 'prompt-manager'),
              value: attributes.warningText,
              onChange: function (value) {
                setAttributes({ warningText: value });
              },
            }),
            createElement(TextControl, {
              label: __('Button Text', 'prompt-manager'),
              value: attributes.buttonText,
              onChange: function (value) {
                setAttributes({ buttonText: value });
              },
            }),
            createElement(
              'div',
              { style: { marginBottom: '20px' } },
              createElement('label', {}, __('Background Color', 'prompt-manager')),
              createElement(ColorPicker, {
                color: attributes.backgroundColor,
                onChangeComplete: function (color) {
                  setAttributes({ backgroundColor: color.hex });
                },
              })
            ),
            createElement(
              'div',
              { style: { marginBottom: '20px' } },
              createElement('label', {}, __('Text Color', 'prompt-manager')),
              createElement(ColorPicker, {
                color: attributes.textColor,
                onChangeComplete: function (color) {
                  setAttributes({ textColor: color.hex });
                },
              })
            )
          )
        )
      );
    },
    save: function () {
      return null; // Rendered by PHP
    },
  });

  // Protected Image Block
  registerBlockType('prompt-manager/protected-image', {
    title: __('Protected Image', 'prompt-manager'),
    icon: 'lock',
    category: 'prompt-manager',
    attributes: {
      imageId: {
        type: 'number',
        default: 0,
      },
      imageUrl: {
        type: 'string',
        default: '',
      },
      alt: {
        type: 'string',
        default: '',
      },
      caption: {
        type: 'string',
        default: '',
      },
      size: {
        type: 'string',
        default: 'large',
      },
      blurIntensity: {
        type: 'number',
        default: 15,
      },
      requireLogin: {
        type: 'boolean',
        default: true,
      },
    },
    edit: function (props) {
      const { attributes, setAttributes } = props;
      const blockProps = useBlockProps();

      return createElement(
        Fragment,
        {},
        createElement(
          'div',
          blockProps,
          createElement(
            'div',
            { className: 'prompt-manager-block-placeholder' },
            attributes.imageId
              ? createElement(
                  'p',
                  {},
                  __('Protected Image ID: ', 'prompt-manager') + attributes.imageId
                )
              : createElement('p', {}, __('Select an image to protect', 'prompt-manager'))
          )
        ),
        createElement(
          InspectorControls,
          {},
          createElement(
            PanelBody,
            { title: __('Image Settings', 'prompt-manager') },
            createElement(TextControl, {
              label: __('Image ID', 'prompt-manager'),
              value: attributes.imageId,
              onChange: function (value) {
                setAttributes({ imageId: parseInt(value) || 0 });
              },
              type: 'number',
            }),
            createElement(TextControl, {
              label: __('Alt Text', 'prompt-manager'),
              value: attributes.alt,
              onChange: function (value) {
                setAttributes({ alt: value });
              },
            }),
            createElement(TextControl, {
              label: __('Caption', 'prompt-manager'),
              value: attributes.caption,
              onChange: function (value) {
                setAttributes({ caption: value });
              },
            }),
            createElement(SelectControl, {
              label: __('Image Size', 'prompt-manager'),
              value: attributes.size,
              options: promptManagerBlocks.imageSizes,
              onChange: function (value) {
                setAttributes({ size: value });
              },
            }),
            createElement(ToggleControl, {
              label: __('Require Login', 'prompt-manager'),
              checked: attributes.requireLogin,
              onChange: function (value) {
                setAttributes({ requireLogin: value });
              },
            }),
            attributes.requireLogin &&
              createElement(RangeControl, {
                label: __('Blur Intensity', 'prompt-manager'),
                value: attributes.blurIntensity,
                onChange: function (value) {
                  setAttributes({ blurIntensity: value });
                },
                min: 5,
                max: 35,
                step: 5,
              })
          )
        )
      );
    },
    save: function () {
      return null; // Rendered by PHP
    },
  });
})();

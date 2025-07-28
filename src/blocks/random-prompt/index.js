import { registerBlockType } from '@wordpress/blocks';
import { createElement, Fragment } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, SelectControl } from '@wordpress/components';

registerBlockType('prompt-manager/random-prompt', {
  title: __('Random Prompt', 'prompt-manager'),
  icon: 'randomize',
  category: 'prompt-manager',
  attributes: {
    showTitle: { type: 'boolean', default: true },
    showExcerpt: { type: 'boolean', default: true },
    showImage: { type: 'boolean', default: true },
    imageSize: { type: 'string', default: 'medium' },
  },
  edit: ({ attributes, setAttributes }) => {
    return createElement(
      Fragment,
      {},
      createElement('div', useBlockProps(), __('Random Prompt', 'prompt-manager')),
      createElement(
        InspectorControls,
        {},
        createElement(
          PanelBody,
          { title: __('Display Settings', 'prompt-manager') },
          createElement(ToggleControl, {
            label: __('Show Title', 'prompt-manager'),
            checked: attributes.showTitle,
            onChange: (value) => setAttributes({ showTitle: value }),
          }),
          createElement(ToggleControl, {
            label: __('Show Excerpt', 'prompt-manager'),
            checked: attributes.showExcerpt,
            onChange: (value) => setAttributes({ showExcerpt: value }),
          }),
          createElement(ToggleControl, {
            label: __('Show Image', 'prompt-manager'),
            checked: attributes.showImage,
            onChange: (value) => setAttributes({ showImage: value }),
          }),
          attributes.showImage &&
            createElement(SelectControl, {
              label: __('Image Size', 'prompt-manager'),
              value: attributes.imageSize,
              options: promptManagerBlocks.imageSizes,
              onChange: (value) => setAttributes({ imageSize: value }),
            })
        )
      )
    );
  },
  save: () => null,
});

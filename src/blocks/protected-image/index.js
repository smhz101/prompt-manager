import { registerBlockType } from '@wordpress/blocks';
import { createElement, Fragment } from '@wordpress/element';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl, RangeControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

registerBlockType('prompt-manager/protected-image', {
  title: __('Protected Image', 'prompt-manager'),
  icon: 'lock',
  category: 'prompt-manager',
  attributes: {
    imageId: { type: 'number', default: 0 },
    imageUrl: { type: 'string', default: '' },
    alt: { type: 'string', default: '' },
    caption: { type: 'string', default: '' },
    size: { type: 'string', default: 'large' },
    blurIntensity: { type: 'number', default: 15 },
    requireLogin: { type: 'boolean', default: true },
  },
  edit: (props) => {
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
            ? createElement('p', {}, __('Protected Image ID: ', 'prompt-manager') + attributes.imageId)
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
            onChange: (value) => setAttributes({ imageId: parseInt(value) || 0 }),
            type: 'number',
          }),
          createElement(TextControl, {
            label: __('Alt Text', 'prompt-manager'),
            value: attributes.alt,
            onChange: (value) => setAttributes({ alt: value }),
          }),
          createElement(TextControl, {
            label: __('Caption', 'prompt-manager'),
            value: attributes.caption,
            onChange: (value) => setAttributes({ caption: value }),
          }),
          createElement(SelectControl, {
            label: __('Image Size', 'prompt-manager'),
            value: attributes.size,
            options: promptManagerBlocks.imageSizes,
            onChange: (value) => setAttributes({ size: value }),
          }),
          createElement(ToggleControl, {
            label: __('Require Login', 'prompt-manager'),
            checked: attributes.requireLogin,
            onChange: (value) => setAttributes({ requireLogin: value }),
          }),
          attributes.requireLogin &&
            createElement(RangeControl, {
              label: __('Blur Intensity', 'prompt-manager'),
              value: attributes.blurIntensity,
              onChange: (value) => setAttributes({ blurIntensity: value }),
              min: 5,
              max: 35,
              step: 5,
            })
        )
      )
    );
  },
  save: () => null,
});

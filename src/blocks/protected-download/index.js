import { registerBlockType } from '@wordpress/blocks';
import { createElement, Fragment } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';

registerBlockType('prompt-manager/protected-download', {
  title: __('Protected Download', 'prompt-manager'),
  icon: 'download',
  category: 'prompt-manager',
  attributes: {
    attachmentId: { type: 'number', default: 0 },
  },
  edit: ({ attributes, setAttributes }) => {
    return createElement(
      Fragment,
      {},
      createElement('div', useBlockProps(), __('Protected Download', 'prompt-manager')),
      createElement(
        InspectorControls,
        {},
        createElement(
          PanelBody,
          { title: __('Download Settings', 'prompt-manager') },
          createElement(TextControl, {
            label: __('Attachment ID', 'prompt-manager'),
            value: attributes.attachmentId,
            onChange: (value) => setAttributes({ attachmentId: parseInt(value) || 0 }),
          })
        )
      )
    );
  },
  save: () => null,
});

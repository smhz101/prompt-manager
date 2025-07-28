import { registerBlockType } from '@wordpress/blocks';
import { createElement, Fragment } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, RangeControl } from '@wordpress/components';

registerBlockType('prompt-manager/advance-query', {
  title: __('Advance Query', 'prompt-manager'),
  icon: 'filter',
  category: 'prompt-manager',
  attributes: {
    postsPerPage: { type: 'number', default: 5 },
    orderBy: { type: 'string', default: 'date' },
    order: { type: 'string', default: 'DESC' },
  },
  edit: ({ attributes, setAttributes }) => {
    return createElement(
      Fragment,
      {},
      createElement('div', useBlockProps(), __('Advance Query', 'prompt-manager')),
      createElement(
        InspectorControls,
        {},
        createElement(
          PanelBody,
          { title: __('Query Settings', 'prompt-manager') },
          createElement(RangeControl, {
            label: __('Posts Per Page', 'prompt-manager'),
            value: attributes.postsPerPage,
            onChange: (value) => setAttributes({ postsPerPage: value }),
            min: 1,
            max: 20,
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
            onChange: (value) => setAttributes({ orderBy: value }),
          }),
          createElement(SelectControl, {
            label: __('Order', 'prompt-manager'),
            value: attributes.order,
            options: [
              { value: 'DESC', label: __('Descending', 'prompt-manager') },
              { value: 'ASC', label: __('Ascending', 'prompt-manager') },
            ],
            onChange: (value) => setAttributes({ order: value }),
          })
        )
      )
    );
  },
  save: () => null,
});

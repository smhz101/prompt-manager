import { registerBlockType } from '@wordpress/blocks';
import { createElement, Fragment } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
  InspectorControls,
  useBlockProps,
  InnerBlocks,
} from '@wordpress/block-editor';
import {
  PanelBody,
  SelectControl,
  RangeControl,
  TextControl,
  ToggleControl,
} from '@wordpress/components';

registerBlockType('prompt-manager/advance-query', {
  title: __('Advance Query', 'prompt-manager'),
  icon: 'filter',
  category: 'prompt-manager',
  attributes: {
    query: {
      type: 'object',
      default: {
        perPage: 5,
        offset: 0,
        postType: 'prompt',
        orderBy: 'date',
        order: 'DESC',
        category: '',
        tag: '',
        sticky: 'include',
        inherit: false,
      },
    },
  },
  edit: ({ attributes, setAttributes }) => {
    const { query } = attributes;
    const updateQuery = (updates) => setAttributes({ query: { ...query, ...updates } });
    const blockProps = useBlockProps();

    return createElement(
      Fragment,
      {},
      createElement(
        'div',
        blockProps,
        createElement(InnerBlocks, {
          orientation: 'vertical',
          template: [
            ['core/post-featured-image'],
            ['core/post-title'],
            ['core/post-excerpt'],
          ],
          templateLock: false,
        })
      ),
      createElement(
        InspectorControls,
        {},
        createElement(
          PanelBody,
          { title: __('Query Settings', 'prompt-manager') },
          createElement(RangeControl, {
            label: __('Posts Per Page', 'prompt-manager'),
            value: query.perPage,
            onChange: (value) => updateQuery({ perPage: value }),
            min: 1,
            max: 20,
          }),
          createElement(RangeControl, {
            label: __('Offset', 'prompt-manager'),
            value: query.offset,
            onChange: (value) => updateQuery({ offset: value }),
            min: 0,
            max: 20,
          }),
          createElement(SelectControl, {
            label: __('Post Type', 'prompt-manager'),
            value: query.postType,
            options: [
              { value: 'prompt', label: __('Prompt', 'prompt-manager') },
              { value: 'post', label: __('Post', 'prompt-manager') },
            ],
            onChange: (value) => updateQuery({ postType: value }),
          }),
          createElement(SelectControl, {
            label: __('Order By', 'prompt-manager'),
            value: query.orderBy,
            options: [
              { value: 'date', label: __('Date', 'prompt-manager') },
              { value: 'title', label: __('Title', 'prompt-manager') },
              { value: 'menu_order', label: __('Menu Order', 'prompt-manager') },
              { value: 'rand', label: __('Random', 'prompt-manager') },
            ],
            onChange: (value) => updateQuery({ orderBy: value }),
          }),
          createElement(SelectControl, {
            label: __('Order', 'prompt-manager'),
            value: query.order,
            options: [
              { value: 'DESC', label: __('Descending', 'prompt-manager') },
              { value: 'ASC', label: __('Ascending', 'prompt-manager') },
            ],
            onChange: (value) => updateQuery({ order: value }),
          }),
          createElement(SelectControl, {
            label: __('Category', 'prompt-manager'),
            value: query.category,
            options: [
              { value: '', label: __('All', 'prompt-manager') },
              ...(promptManagerBlocks.categories || []),
            ],
            onChange: (value) => updateQuery({ category: value }),
          }),
          createElement(SelectControl, {
            label: __('Tag', 'prompt-manager'),
            value: query.tag,
            options: [
              { value: '', label: __('All', 'prompt-manager') },
              ...(promptManagerBlocks.tags || []),
            ],
            onChange: (value) => updateQuery({ tag: value }),
          }),
          createElement(SelectControl, {
            label: __('Sticky Posts', 'prompt-manager'),
            value: query.sticky,
            options: [
              { value: 'include', label: __('Include', 'prompt-manager') },
              { value: 'exclude', label: __('Exclude', 'prompt-manager') },
              { value: 'only', label: __('Only Sticky', 'prompt-manager') },
            ],
            onChange: (value) => updateQuery({ sticky: value }),
          }),
          createElement(ToggleControl, {
            label: __('Inherit Global Query', 'prompt-manager'),
            checked: query.inherit,
            onChange: (value) => updateQuery({ inherit: value }),
          }),
          createElement(TextControl, {
            label: __('Search Keyword', 'prompt-manager'),
            value: query.search || '',
            onChange: (value) => updateQuery({ search: value }),
          })
        )
      )
    );
  },
  save: () => null,
});

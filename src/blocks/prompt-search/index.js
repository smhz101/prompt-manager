import { registerBlockType } from '@wordpress/blocks';
import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';

registerBlockType('prompt-manager/prompt-search', {
  title: __('Prompt Search', 'prompt-manager'),
  icon: 'search',
  category: 'prompt-manager',
  edit: () => createElement('div', useBlockProps(), __('Prompt Search Block', 'prompt-manager')),
  save: () => null,
});

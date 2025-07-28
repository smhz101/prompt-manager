const fs = require('fs');
const path = require('path');

describe('Block imports', () => {
  const indexPath = path.join(__dirname, '..', 'src', 'index.js');
  const content = fs.readFileSync(indexPath, 'utf8');

  const blocks = [
    'prompt-display',
    'prompt-gallery',
    'nsfw-warning',
    'protected-image',
    'prompt-search',
    'analytics-summary',
    'random-prompt',
    'prompt-submission',
    'protected-download',
    'prompt-slider',
    'advance-query',
  ];

  test('index.js imports all blocks', () => {
    blocks.forEach((block) => {
      const expected = `./blocks/${block}`;
      expect(content.includes(expected)).toBe(true);
    });
  });

  test('each block directory has index.js', () => {
    blocks.forEach((block) => {
      const blockPath = path.join(__dirname, '..', 'src', 'blocks', block, 'index.js');
      expect(fs.existsSync(blockPath)).toBe(true);
    });
  });

  test('each block directory has block.json', () => {
    blocks.forEach((block) => {
      const jsonPath = path.join(__dirname, '..', 'src', 'blocks', block, 'block.json');
      expect(fs.existsSync(jsonPath)).toBe(true);
    });
  });
});

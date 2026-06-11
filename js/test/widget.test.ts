// @vitest-environment happy-dom
import { afterEach, describe, expect, it } from 'vitest';
import { mountFromGlobalConfig } from '../src/widget/index';
import { createWidget } from '../src/widget/widget';

describe('createWidget (DOM smoke)', () => {
  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('mounts the widget and injects styles', () => {
    const widget = createWidget({
      endpointBase: '/file-upload',
      routes: { upload: '/file-upload/chunks', list: '/file-upload/files' },
      allowList: true,
      allowDelete: true,
    });

    expect(widget.root).toBeInstanceOf(HTMLElement);
    expect(widget.root.isConnected).toBe(true);
    expect(widget.root.classList.contains('fu-widget')).toBe(true);
    expect(widget.root.querySelector('.fu-toggle')).not.toBeNull();
    expect(widget.root.querySelector('.fu-dropzone')).not.toBeNull();
    expect(widget.root.querySelector('input[type="file"]')).not.toBeNull();
    expect(document.getElementById('file-upload-widget-styles')).not.toBeNull();
  });

  it('omits the file list when allowList is false', () => {
    const widget = createWidget({ allowList: false });
    expect(widget.root.querySelector('.fu-list')).toBeNull();
  });

  it('destroy() detaches the widget from the DOM', () => {
    const widget = createWidget();
    expect(widget.root.isConnected).toBe(true);
    widget.destroy();
    expect(widget.root.isConnected).toBe(false);
  });

  it('mountFromGlobalConfig returns null when no global config is set', () => {
    expect(mountFromGlobalConfig()).toBeNull();
  });
});

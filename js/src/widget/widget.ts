import { createController } from './controller';
import { mountFormTemplate } from './templates/form';
import { mountWidgetTemplate } from './templates/widget';
import type { WidgetConfig, WidgetInstance } from './types';

/**
 * Build and mount an uploader, picking the layout from `config.template`
 * (`'widget'` — floating button + modal, default; or `'form'` — inline panel).
 * Shared logic lives in the controller; templates only arrange the parts.
 *
 * To add a third layout: write `templates/<name>.ts` exporting a
 * `mount…Template(controller)` and add one case below — no public registry.
 */
export function createUploader(config: WidgetConfig = {}): WidgetInstance {
  const controller = createController(config);

  switch (config.template) {
    case 'form':
      return mountFormTemplate(controller);
    case 'widget':
    default:
      return mountWidgetTemplate(controller);
  }
}

/** Mount the floating-widget layout. Signature/behaviour unchanged from before. */
export function createWidget(config: WidgetConfig = {}): WidgetInstance {
  return createUploader({ ...config, template: 'widget' });
}

/** Mount the inline-form layout into `config.container` (required). */
export function createForm(config: WidgetConfig = {}): WidgetInstance {
  return createUploader({ ...config, template: 'form' });
}

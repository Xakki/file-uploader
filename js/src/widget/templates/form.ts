import { type Controller, el, toInstance } from '../controller';
import type { WidgetInstance } from '../types';

/**
 * Inline panel mounted into `config.container` (required): a titled card with
 * the dropzone, status, upload queue and — visible by default, no toggle — the
 * already-uploaded file list. Suited to a page section / landing centerpiece.
 */
export function mountFormTemplate(controller: Controller): WidgetInstance {
  const { config, strings, root, parts } = controller;
  const { dropzone, status, queueContainer, listContainer, refreshButton } = parts;

  if (!controller.hasContainer) {
    throw new Error("The 'form' template requires config.container to mount into.");
  }

  // Reuse the shared `.fu-form` surface styling; keep `.fu-widget` for theming.
  root.classList.add('fu-form');

  const header = el('div', 'fu-header');
  const title = el('h2', 'fu-title');
  title.textContent = strings.title;
  header.appendChild(title);

  if (config.allowList && listContainer) {
    header.appendChild(refreshButton);
  }

  root.append(header, dropzone, status, queueContainer);

  if (config.allowList && listContainer) {
    // Visible by default — the form has no expand/collapse affordance.
    listContainer.classList.add('fu-open');
    root.appendChild(listContainer);
    // Show persisted files immediately (also covers the refresh-on-mount bugfix).
    void controller.fetchFiles();
  }

  return toInstance(controller);
}

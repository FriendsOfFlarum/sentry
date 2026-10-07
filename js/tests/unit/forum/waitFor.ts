/**
 * Optional integrations arrive through a dynamic import, so give it a few ticks to resolve.
 */
export default async function waitFor(condition: () => boolean, attempts = 50): Promise<boolean> {
  for (let i = 0; i < attempts && !condition(); i++) {
    await new Promise((resolve) => setTimeout(resolve, 10));
  }

  return condition();
}

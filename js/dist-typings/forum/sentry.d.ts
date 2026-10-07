import { BrowserClient } from '@sentry/browser';
import type { User } from '@sentry/browser';
/**
 * The browser configuration the backend puts in the forum payload (see SentryJavaScript.php).
 */
export interface SentryConfig {
    dsn: string;
    environment?: string;
    release?: string;
    scrubEmails?: boolean;
    showFeedback?: boolean;
    captureConsole?: boolean;
    tracesSampleRate?: number;
    replaysSessionSampleRate?: number;
    replaysOnErrorSampleRate?: number;
    tags?: Record<string, string>;
}
export declare function getUserData(nameAttr?: string): User;
export declare function createClient(config: SentryConfig): BrowserClient;

const PASSWORD_COMPLEXITY_MESSAGE =
    'Password must contain at least one uppercase letter, one lowercase letter, one number, one special character, and meet the minimum length requirement.';

const COMPLEXITY_HINTS = [
    'uppercase',
    'lowercase',
    'letters',
    'mixed case',
    'number',
    'numeric',
    'symbol',
    'special character',
    'at least',
    'characters',
    'password format',
];

export function normalizePasswordValidationMessage(message?: string): string | undefined {
    if (!message) {
        return undefined;
    }

    const normalized = message.toLowerCase();

    if (COMPLEXITY_HINTS.some((hint) => normalized.includes(hint))) {
        return PASSWORD_COMPLEXITY_MESSAGE;
    }

    return message;
}

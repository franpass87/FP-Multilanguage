/**
 * API Client per chiamate REST
 * @since 0.3.2
 */

/**
 * Esegue una richiesta POST all'endpoint specificato
 */
export const performAction = async (endpoint, nonce) => {
    if (!endpoint || typeof window.fetch !== 'function') {
        throw new Error('Endpoint non valido o fetch non disponibile');
    }

    const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': nonce,
        },
        credentials: 'same-origin',
        body: '{}',
    });

    const payload = await response.json();

    if (!response.ok || !payload || payload.success !== true) {
        const message = (payload && payload.message) || 
                       (payload && payload.data && payload.data.message) || 
                       'Operazione non riuscita.';
        throw new Error(message);
    }

    return payload;
};
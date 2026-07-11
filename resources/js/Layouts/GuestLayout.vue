<template>
    <main class="auth-page">
        <slot />
    </main>
</template>

<style>
.auth-page {
    display: flex;
    min-height: 100vh;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: var(--app-bg);
}

.auth-box {
    width: 360px;
    max-width: 100%;
}

.auth-card {
    margin-bottom: 0;
    overflow: hidden;
    border: 1px solid var(--app-border);
    border-top: 3px solid var(--app-accent);
    border-radius: 0.75rem;
    background: var(--app-surface);
    color: var(--app-text);
    box-shadow: 0 0.75rem 2rem rgba(0, 0, 0, 0.08);
}

.auth-card .card-header,
.auth-card-body {
    background: var(--app-surface);
    color: var(--app-text);
}

.auth-card-body {
    padding: 1.25rem;
}

.auth-message {
    margin: 0;
    padding: 0 1.25rem 1.25rem;
    color: var(--app-text-muted);
    text-align: center;
}

@media (max-width: 575.98px) {
    .auth-page {
        align-items: flex-start;
        padding: 14px 10px;
    }

    .auth-box {
        width: 100%;
        margin: auto 0;
    }

    .auth-card {
        border-radius: 14px;
    }

    .auth-card .card-header {
        padding: 14px;
    }

    .auth-card-body {
        padding: 16px 14px;
    }

    .auth-message {
        padding: 0 0 16px;
    }
}
</style>

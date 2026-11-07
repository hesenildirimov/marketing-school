/* Minimum doldurma vaxtı üçün səhifənin açılma anı (bot qoruması, server yoxlayır). */
document.querySelectorAll('form input[name="ts"]').forEach((i) => { i.value = String(Date.now()); });

// Test simple para verificar sincronización
document.addEventListener('DOMContentLoaded', function() {
    console.log('Test de sincronización iniciado');

    // Verificar que el Service Worker está registrado
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistrations().then(registrations => {
            console.log('Service Workers registrados:', registrations.length);
            registrations.forEach(reg => {
                console.log('SW:', reg.scope);
            });
        });
    }

    // Verificar que IndexedDB funciona
    if (window.indexedDB) {
        console.log('IndexedDB soportado');
    }

    // Test simple de API
    fetch('/api/user', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        console.log('API /api/user funciona:', data);
    })
    .catch(error => {
        console.error('Error en API /api/user:', error);
    });

    // Test de sincronización
    setTimeout(() => {
        if (window.syncCompanies) {
            console.log('Probando syncCompanies...');
            window.syncCompanies().then(result => {
                console.log('syncCompanies exitoso:', result);
            }).catch(error => {
                console.error('Error en syncCompanies:', error);
            });
        } else {
            console.log('syncCompanies no disponible');
        }
    }, 2000);
});
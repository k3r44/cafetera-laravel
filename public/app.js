// 1. Eliminamos el token fijo. Ahora usamos la variable 'token' global.
let token = localStorage.getItem('coffee_token'); 
let bebidaSeleccionada = null;

// 🔥 EL CAMBIO MAESTRO: Ruta relativa. 
// Ahora el navegador autocompletará el dominio/IP del servidor donde esté alojado.
const API_BASE = "/api/v1"; 

window.onload = () => {
    const savedToken = localStorage.getItem('coffee_token');
    const savedName = localStorage.getItem('user_name'); 

    if (savedToken && savedName) {
        token = savedToken;
        
        // Configuramos la UI con los datos guardados
        if (typeof setupUserUI === "function") setupUserUI(savedName);

        // Saltamos el login
        document.getElementById('login-screen').classList.add('hidden');
        document.getElementById('app').classList.add('visible');
        document.getElementById('user-bar').classList.add('visible');
        
        // Si usas el carrusel, lo iniciamos
        if (typeof buildCarousel === "function") buildCarousel();
    }
};

// Modificamos tu función de Login para que guarde el nombre
async function doLogin() {
    // Asegúrate de capturar los valores de tus inputs aquí
    const user = document.getElementById('username').value; // Ajusta el ID según tu HTML
    const pass = document.getElementById('password').value; // Ajusta el ID según tu HTML

    try {
        const res = await fetch(`${API_BASE}/login`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username: user, password: pass })
        });
        
        const data = await res.json();
        
        if (res.ok) {
            token = data.token;
            
            // GUARDAMOS AMBOS EN EL NAVEGADOR
            localStorage.setItem('coffee_token', token);
            localStorage.setItem('user_name', data.user); 

            if (typeof setupUserUI === "function") setupUserUI(data.user);
            
            document.getElementById('login-screen').classList.add('hidden');
            document.getElementById('app').classList.add('visible');
            document.getElementById('user-bar').classList.add('visible');
            
            if (typeof buildCarousel === "function") buildCarousel();
        } else {
            alert(data.message || "Credenciales incorrectas");
        }
    } catch (e) { 
        console.error("Error conectando al servidor:", e);
    }
}

// 3. LA FUNCIÓN DE PEDIR (Ahora usa el token dinámico)
function prepararSeleccionado() {
    if (!token) {
        alert("Sesión expirada. Por favor ingresa de nuevo.");
        return;
    }

    if (!bebidaSeleccionada) {
        alert("Por favor selecciona una bebida primero.");
        return;
    }

    const status = document.getElementById("status");
    if(status) status.innerText = "Enviando orden...";

    fetch(`${API_BASE}/pedir`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            // USAMOS EL TOKEN DEL USUARIO LOGUEADO
            "Authorization": "Bearer " + token 
        },
        body: JSON.stringify({ metodo: bebidaSeleccionada })
    })
    .then(res => {
        if(res.status === 401) throw new Error("Token no válido");
        if(res.status === 429) throw new Error("Cafetera ocupada");
        if(!res.ok) throw new Error("Error en el servidor");
        return res.json();
    })
    .then(data => {
        if(status) status.innerText = "¡Orden aceptada!";
        
        // Verificamos que el navegador soporte la síntesis de voz
        if ('speechSynthesis' in window) {
            const speech = new SpeechSynthesisUtterance("Preparando " + bebidaSeleccionada);
            window.speechSynthesis.speak(speech);
        }
    })
    .catch((err) => {
        if(status) {
            if(err.message === "Cafetera ocupada") {
                status.innerText = "La cafetera está en uso, espera un momento.";
            } else {
                status.innerText = "Error: Sesión no válida o servidor apagado";
            }
        }
        console.error(err);
    });
}

// Función auxiliar para cerrar sesión
function logout() {
    localStorage.removeItem('coffee_token');
    localStorage.removeItem('user_name');
    location.reload();
}
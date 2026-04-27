// 1. Eliminamos el token fijo. Ahora usamos la variable 'token' global.
let token = localStorage.getItem('coffee_token'); 
let bebidaSeleccionada = null;
const API_BASE = "http://localhost:8000/api/v1"; // Usa localhost o tu IP

window.onload = () => {
    const savedToken = localStorage.getItem('coffee_token');
    const savedName = localStorage.getItem('user_name'); // Guardaremos también el nombre

    if (savedToken && savedName) {
        token = savedToken;
        
        // Configuramos la UI con los datos guardados
        setupUserUI(savedName);

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
    // ... (tu lógica de captura de inputs)
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

            setupUserUI(data.user);
            
            document.getElementById('login-screen').classList.add('hidden');
            document.getElementById('app').classList.add('visible');
            document.getElementById('user-bar').classList.add('visible');
            buildCarousel();
        } 
        // ...
    } catch (e) { /*...*/ }
}

// 3. LA FUNCIÓN DE PEDIR (Ahora usa el token dinámico)
function prepararSeleccionado() {
  if (!token) {
      alert("Sesión expirada. Por favor ingresa de nuevo.");
      return;
  }

  const status = document.getElementById("status");
  status.innerText = "Enviando orden...";

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
      return res.json();
  })
  .then(data => {
    status.innerText = "¡Orden aceptada!";
    const speech = new SpeechSynthesisUtterance("Preparando " + bebidaSeleccionada);
    window.speechSynthesis.speak(speech);
  })
  .catch((err) => {
      status.innerText = "Error: Sesión no válida o servidor apagado";
      console.error(err);
  });
}

// Función auxiliar para cerrar sesión
function logout() {
    localStorage.removeItem('coffee_token');
    localStorage.removeItem('user_name');
    location.reload();
}
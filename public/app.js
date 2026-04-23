let bebidaSeleccionada = null;
const MI_TOKEN = "G0c9dLYLYDdpeNVCNfLXjXRETxI0z3aoPD3I8edIaf731a78"; 

function seleccionarBebida(nombre, elemento) {
  document.querySelectorAll('.drink-card').forEach(c => c.classList.remove('selected'));
  elemento.classList.add('selected');
  bebidaSeleccionada = nombre;
  document.getElementById("btn-preparar").disabled = false;
  document.getElementById("status").innerText = "Seleccionado: " + nombre;
}

function prepararSeleccionado() {
  const status = document.getElementById("status");
  status.innerText = "Enviando orden...";

  fetch("http://192.168.0.101:8000/api/v1/pedir", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "Authorization": "Bearer " + MI_TOKEN
    },
    body: JSON.stringify({ metodo: bebidaSeleccionada })
  })
  .then(res => res.json())
  .then(data => {
    status.innerText = "¡Orden aceptada!";
    const speech = new SpeechSynthesisUtterance("Preparando " + bebidaSeleccionada);
    window.speechSynthesis.speak(speech);
  })
  .catch(() => status.innerText = "Error: Verifica tu servidor Laravel");
}
  /**
   * ESTADO GLOBAL
   */
  const state = {
      isAuthenticated: false, // Bandera sesión (migrado SP2-2)
      usuarioActual: null,
      cardData: {
          name: '',
          bio: '',
          avatarUrl: 'src/assets/HideoKojima.png', // Avatar por defecto
          color: '#3b82f6'
      }
  };

  /**
   *  REFERENCIAS AL DOOM
   */
  const dom = {
      inputName: document.getElementById('input-name'),
      inputBio: document.getElementById('input-bio'),
      inputColor: document.getElementById('input-color'),
      inputSearch: document.getElementById('github-search'),
      btnFetch: document.getElementById('btn-fetch'),
      apiStatus: document.getElementById('api-status'),
      form: document.getElementById('profile-form'),
      avatarSelector: document.getElementById('avatar-selector'),
      previewName: document.getElementById('name-preview'),
      previewBio: document.getElementById('bio-preview'),
      previewAvatar: document.getElementById('avatar-preview'),
      previewHeader: document.getElementById('preview-header'),
      viewCreate: document.getElementById('view-create'),
      viewGallery: document.getElementById('view-gallery'),
      navLinks: document.querySelectorAll('.nav-link'),
      galleryContainer: document.getElementById('gallery-container'),
      btnReloadGallery: document.getElementById('btn-reload-gallery'),
      // Módulo Auth (migrado SP2-2)
      viewAuth: document.getElementById('view-auth'),
      tabLogin: document.getElementById('tab-login'),
      tabRegister: document.getElementById('tab-register'),
      formLogin: document.getElementById('form-login'),
      formRegistro: document.getElementById('form-registro'),
      navCrear: document.getElementById('nav-crear'),
      navGaleria: document.getElementById('nav-galeria'),
      navLogout: document.getElementById('nav-logout'),
      navLogin: document.querySelector('a[href="#/login"]'),
  };
    /**
     * ENDPOINT ÚNICO
     * Todas las peticiones al backend pasan por este mismo archivo,
     * diferenciadas por el parámetro "action".
     */
    const API_ENDPOINT = './api/index.php';

  /**
     * RUTA DE LOS RECURSOS ESTATICOS
     */
    const ASSETS_PATH = 'src/assets/';

    /**
     * CATALOGO DE AVATARES
     * Los archivos que existen realmente en src/assets/. Antes el codigo
     * asumia nombres tipo "Avatares0.png" que no existen, por eso el
     * selector mostraba imagenes rotas.
     */
    const AVATARES = [
        'CaraFeliz_xbox360.png',
        'ChinesePanda.png',
        'ClubPenguin.png',
        'Fletcher.png',
        'Gato.png',
        'HideoKojima.png',
        'JackFrost.png',
        'Kagami.png',
        'KratosUtorrent.png',
        'Megumin.png',
        'NecoArc.png',
        'panda_xbox360.png',
        'perro_xbox360.png',
        'WittyAlien.png'
    ];
    const rutaAvatar = (archivo) => ASSETS_PATH + archivo;

    // Quitamos la extensión para usarla como texto alternativo legible.
    const nombreAvatar = (ruta) => ruta
        .split('/')
        .pop()
        .replace(/\.png$/i, '')
        .replace(/_/g, ' ');

  /**
   * GENERADOR DEL SELECTOR DE AVATARES
   * Crea un <img> por cada archivo del catalogo AVATARES.
   */
  const initAvatarSelector = () => {
      AVATARES.forEach((archivo) => {
          const rutaImagen = rutaAvatar(archivo);
          const img = document.createElement('img');
          img.src = rutaImagen;
          img.alt = nombreAvatar(rutaImagen);
          img.className = 'avatar-option';
          // Guardamos la ruta en un atributo de datos para leerlo fácilmente
          img.dataset.url = rutaImagen;

          // Si el archivo llegara a faltar, no dejamos un ícono roto visible.
          img.addEventListener('error', () => {
              img.style.display = 'none';
          });

          // Evento: al hacer clic en una miniatura, actualizamos el estado global
          img.addEventListener('click', () => {
              state.cardData.avatarUrl = rutaImagen;
              renderCard(); // Forzamos la actualización de la UI
          });

          dom.avatarSelector.appendChild(img);  // Insertar la imagen en el contenedor
      });
  };

  /**
   * FUNCION DE RENDERIZADO (Reactividad)
   */
  const renderCard = () => {
      dom.previewName.textContent = state.cardData.name || 'Nombre Apellido';
      dom.previewBio.textContent = state.cardData.bio || 'La biografía aparecerá aquí...';
      dom.previewAvatar.src = state.cardData.avatarUrl;
      dom.previewHeader.style.backgroundColor = state.cardData.color;
      dom.inputName.value = state.cardData.name;
      dom.inputBio.value = state.cardData.bio;
      dom.inputColor.value = state.cardData.color;
      // Actualizar visualmente qué avatar está seleccionado en la cuadrícula
      const avatares = dom.avatarSelector.querySelectorAll('.avatar-option');
      avatares.forEach(img => {
          if (img.dataset.url === state.cardData.avatarUrl) {
              img.classList.add('selected');
          } else {
              img.classList.remove('selected');
          }
      });
  };

  /**
   * LIMPIAR EL FORMULARIO Y LA TARJETA
   * Restablece los valores a su estado por defecto
   */
  const resetCardState = () => {
      // 1. Restablecemos el estado global
      state.cardData = {
          name: '',
          bio: '',
          avatarUrl: 'src/assets/HideoKojima.png', // mismo default que state inicial, existe en src/assets
          color: '#3b82f6'
      };

      // 2. Limpiamos el campo de búsqueda de GitHub si se usó
      dom.inputSearch.value = '';
      dom.apiStatus.textContent = '';
      dom.apiStatus.className = 'status-msg';

      // 3. Forzamos el renderizado para que la vista previa y los inputs se limpien
      renderCard();
  };

  /**
   * EVENTOS MANUALES
   */
  const setupManualEvents = () => {
      dom.inputName.addEventListener('input', (e) => {
          state.cardData.name = e.target.value; // Guardar nombre
          renderCard(); // Actualizar tarjeta
      });

      dom.inputBio.addEventListener('input', (e) => {
          state.cardData.bio = e.target.value;
          renderCard();
      });

      dom.inputColor.addEventListener('input', (e) => {
          state.cardData.color = e.target.value; // Guardar color
          renderCard();
      });

      dom.form.addEventListener('submit', (e) => {
          e.preventDefault(); // Evitar recargar la página al enviar el formulario
          saveCardToServer();
      });
  };

  /**
   * CONSUMO DE API Github
   */
  const fetchGitHubData = async (username) => {
    if (!username) return;

    dom.apiStatus.textContent = 'Buscando usuario en GitHub...';
    dom.apiStatus.className = 'status-msg status-loading';
    dom.btnFetch.disabled = true;

    try {
      const response = await fetch(`https://api.github.com/users/${username}`);

      if (!response.ok) throw new Error('Usuario no encontrado');

      const data = await response.json();

      state.cardData.name = data.name || data.login;
      state.cardData.bio = data.bio || 'Este usuario no tiene biografía pública.';
      state.cardData.avatarUrl = data.avatar_url;

      renderCard();

      dom.apiStatus.textContent = '¡Datos cargados correctamente!';
      dom.apiStatus.className = 'status-msg status-success';

    } catch (error) {
      dom.apiStatus.textContent = error.message;
      dom.apiStatus.className = 'status-msg status-error';
    } finally {
      dom.btnFetch.disabled = false;
      setTimeout(() => dom.apiStatus.textContent = '', 3000);
    }
  };

  /**
   * CONSUMO DE API INTERNA (Guardar en el servidor)
   */
  const saveCardToServer = async () => {
    if (!state.cardData.name.trim()) {
      alert('Por favor, ingresa un nombre para la tarjeta.');
      return;
    }

    const btnSubmit = dom.form.querySelector('button[type="submit"]');
    const textoOriginal = btnSubmit.textContent;
    btnSubmit.textContent = 'Guardando en servidor...';
    btnSubmit.disabled = true;

    try {
      const formData = new FormData();
      formData.append('datos_tarjeta', JSON.stringify(state.cardData));

      const response = await fetch(`${API_ENDPOINT}?action=crear_tarjeta`, {
        method: 'POST',
        body: formData
      });

      const textoCrudo = await response.text();
      console.log("Respuesta cruda del servidor:", textoCrudo);

      // Como PHP ahora siempre devuelve JSON, lo parseamos directamente
      const result = JSON.parse(textoCrudo);

      if (!response.ok) {
        // Lanzamos el error específico que nos mandó PHP
        throw new Error(result.error || `El servidor rechazó la petición.`);
      }

      alert(result.message);

    } catch (error) {
      alert('Error: ' + error.message);
      console.error('Error capturado en el catch:', error);
    } finally {
      btnSubmit.textContent = textoOriginal;
      btnSubmit.disabled = false;
    }
  };

  /**
   * LÓGICA DE LA GALERÍA (Consumo y Renderizado)
   */
  const loadGalleryData = async () => {
    dom.galleryContainer.innerHTML = '<p class="status-msg status-loading">Cargando tarjetas...</p>';

    try {
      const response = await fetch(`${API_ENDPOINT}?action=listar_tarjeta`);

      if (!response.ok) {
        throw new Error('Error al cargar la galería.');
      }

      const result = await response.json();

      if (!result.success) {
        throw new Error(result.error || 'No se pudieron cargar las tarjetas.');
      }

      const tarjetas = result.data || [];

      if (tarjetas.length === 0) {
        throw new Error('La galería está vacía.');
      }

      dom.galleryContainer.innerHTML = '';

      tarjetas.reverse().forEach(tarjeta => {
        const cardHTML = `
          <article class="card">
            <div class="card-header" style="background-color: ${tarjeta.color}"></div>
            <img src="${tarjeta.avatarUrl}" alt="Avatar de ${tarjeta.name}" class="avatar">
            <div class="card-body">
              <h3>${tarjeta.name}</h3>
              <p style="font-size: 0.95rem; color: #64748b;">${tarjeta.bio}</p>
            </div>
          </article>
        `;
        dom.galleryContainer.insertAdjacentHTML('beforeend', cardHTML);
      });

      // Las tarjetas pueden traer URLs de avatar que ya no existen (por ejemplo
      // las guardadas cuando los archivos tenían otros nombres). En ese caso
      // caemos al avatar por defecto en lugar de mostrar un ícono roto.
      dom.galleryContainer.querySelectorAll('img.avatar').forEach((img) => {
        img.addEventListener('error', () => {
          img.src = state.cardData.avatarUrl;
        }, { once: true });
      });

    } catch (error) {
      dom.galleryContainer.innerHTML = `<p class="status-msg status-error">${error.message}</p>`;
    }
  };

  /**
   * ACTUALIZAR INTERFAZ DE AUTENTICACIÓN
   * Oculta/muestra botones según si el usuario está logueado
   */
  const updateAuthUI = () => {
      if (state.isAuthenticated) {
          dom.navCrear.style.display = 'block';
          dom.navGaleria.style.display = 'block';
          dom.navLogout.style.display = 'block';
          dom.navLogin.style.display = 'none'; // Ocultamos el botón "Iniciar Sesión"
      } else {
          dom.navCrear.style.display = 'none';
          dom.navGaleria.style.display = 'none';
          dom.navLogout.style.display = 'none';
          dom.navLogin.style.display = 'block';
      }
  };

  const showView = (viewToShow) => {
      [dom.viewAuth, dom.viewCreate, dom.viewGallery].forEach(v => {
          if (!v) return;
          const active = v === viewToShow;
          v.classList.toggle('active', active);
          v.style.display = active ? 'block' : 'none';
      });
  };

  /**
   * EVENTOS DE AUTENTICACIÓN (Cambio de pestañas)
   */
  const setupAuthEvents = () => {
      if (dom.tabLogin && dom.tabRegister) {
          dom.tabLogin.addEventListener('click', () => {
              dom.tabLogin.classList.add('active');
              dom.tabRegister.classList.remove('active');
              dom.formLogin.classList.add('active');
              dom.formLogin.style.display = 'block';
              dom.formRegistro.classList.remove('active');
              dom.formRegistro.style.display = 'none';
          });
          dom.tabRegister.addEventListener('click', () => {
              dom.tabRegister.classList.add('active');
              dom.tabLogin.classList.remove('active');
              dom.formRegistro.classList.add('active');
              dom.formRegistro.style.display = 'block';
              dom.formLogin.classList.remove('active');
              dom.formLogin.style.display = 'none';
          });
      }

      if (dom.formRegistro) {
          dom.formRegistro.addEventListener('submit', async (e) => {
              e.preventDefault(); // Evita la recarga de la página

              const btnSubmit = dom.formRegistro.querySelector('button[type="submit"]');
              const textoOriginal = btnSubmit.textContent;
              btnSubmit.textContent = 'Registrando...';
              btnSubmit.disabled = true;

              try {
                  // FormData captura automáticamente todos los campos con atributo 'name'
                  const formData = new FormData(dom.formRegistro);

                  const response = await fetch(`${API_ENDPOINT}?action=registrar_usuario`, {
                      method: 'POST',
                      body: formData
                  });

                  const result = await response.json();

                  if (!response.ok) {
                      throw new Error(result.error || 'Error al procesar el registro.');
                  }

                  alert(result.message || 'Registro exitoso. Ahora inicia sesión.');
                  dom.formRegistro.reset();
                  dom.tabLogin.click();
              } catch (error) {
                  alert('Error: ' + error.message);
                  console.error('Error en registro:', error);
              } finally {
                  btnSubmit.textContent = textoOriginal;
                  btnSubmit.disabled = false;
              }
          });
      }

      // PROCESO DE INICIO Y CIERRE DE SESIÓN
      // Evento de Iniciar Sesión
      if (dom.formLogin) {
          dom.formLogin.addEventListener('submit', async (e) => {
              e.preventDefault();

              const btnSubmit = dom.formLogin.querySelector('button[type="submit"]');
              const textoOriginal = btnSubmit.textContent;
              btnSubmit.textContent = 'Verificando...';
              btnSubmit.disabled = true;

              try {
                  const formData = new FormData(dom.formLogin);
                  const response = await fetch(`${API_ENDPOINT}?action=login`, {
                      method: 'POST',
                      body: formData
                  });

                  const result = await response.json();

                  if (!response.ok) throw new Error(result.error || 'Error de autenticación');

                  // ¡Éxito! Actualizamos estado global
                  state.isAuthenticated = true;
                  state.usuarioActual = result.data;

                  updateAuthUI();
                  dom.formLogin.reset();

                  // Redirigimos a la creación de perfil
                  window.location.hash = '#/crear';
              } catch (error) {
                  alert('Error: ' + error.message);
              } finally {
                  btnSubmit.textContent = textoOriginal;
                  btnSubmit.disabled = false;
              }
          });
      }

      // Evento de Cerrar Sesión
      if (dom.navLogout) {
          dom.navLogout.addEventListener('click', async (e) => {
              e.preventDefault();
              try {
                  await fetch(`${API_ENDPOINT}?action=logout`);

                  // Limpiamos estado
                  state.isAuthenticated = false;
                  state.usuarioActual = null;
                  try { localStorage.removeItem('sesion'); } catch {}
                  // 2 Limpiamos los datos de las tarjetas (Solución al problema)
                  resetCardState();
                  updateAuthUI();
                  window.location.hash = '#/login'; // Expulsamos al login
              } catch (error) {
                  console.error('Error cerrando sesión:', error);
              }
          });
      }

      updateAuthUI();
  };

  /**
   * ENRUTADOR BASADO EN HASH (El corazón de la SPA - Nueva versión)
   */
  const handleRouting = () => {
    let currentHash = window.location.hash || '#/login';

    // GUARDIÁN DE RUTAS (Route Guard)
    // Si no está autenticado y trata de ir a crear o galería, lo regresamos al login
    if (!state.isAuthenticated && currentHash !== '#/login') {
      window.location.hash = '#/login';
      return; // Detenemos la ejecución; el cambio de hash volverá a disparar la función
    }

    dom.navLinks.forEach(link => {
      if (link.getAttribute('href') === currentHash) {
        link.classList.add('active');
      } else {
        link.classList.remove('active');
      }
    });

    // Ocultar todas las vistas
    dom.viewCreate.classList.remove('active');
    dom.viewCreate.style.display = 'none';
    dom.viewGallery.classList.remove('active');
    dom.viewGallery.style.display = 'none';
    dom.viewAuth.classList.remove('active');
    dom.viewAuth.style.display = 'none';

    // Mostrar la vista correspondiente
    if (currentHash === '#/galeria') {
      dom.viewGallery.style.display = 'block';
      setTimeout(() => dom.viewGallery.classList.add('active'), 10);
      loadGalleryData();
    } else if (currentHash === '#/crear') {
      dom.viewCreate.style.display = 'block';
      setTimeout(() => dom.viewCreate.classList.add('active'), 10);
    } else {
      dom.viewAuth.style.display = 'block';
      setTimeout(() => dom.viewAuth.classList.add('active'), 10);
    }
  };

  const checkActiveSession = async () => {
    try {
      const response = await fetch(`${API_ENDPOINT}?action=check_session`);
      const result = await response.json();

      if (response.ok && result.success) {
        state.isAuthenticated = true;
        state.usuarioActual = result.data;
        updateAuthUI();
      }
    } catch (error) {
      console.error("No hay sesión activa o hubo un error:", error);
    } finally {
      // Una vez comprobado, arrancamos el enrutador
      handleRouting();
    }
  };

  /**
   * INICIALIZACION DE LA APLICACION
   */
  document.addEventListener('DOMContentLoaded', () => {
      initAvatarSelector(); // Inicializamos la cuadrícula de imágenes
      setupManualEvents(); // Configurar eventos del formulario
      setupAuthEvents(); // Auth migrado SP2-2 (tabs + registro + login + logout)

      // Eliminamos la llamada directa a handleRouting() y ponemos:
      checkActiveSession(); // Verifica si ya está logueado y luego enruta

      dom.btnFetch.addEventListener('click', () => {
        const username = dom.inputSearch.value.trim();
        fetchGitHubData(username);
      });

      dom.inputSearch.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          dom.btnFetch.click();
        }
      });
      renderCard(); // Renderizar la tarjeta inicial

      window.addEventListener('hashchange', handleRouting);
      dom.btnReloadGallery.addEventListener('click', loadGalleryData);
  });

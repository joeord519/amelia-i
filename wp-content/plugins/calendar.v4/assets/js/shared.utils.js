// /assets/js/shared.utils.js

function getCookieValue(name) {
  const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
  return match ? decodeURIComponent(match[2]) : null;
}

function showUserInfo() {
  const name = getCookieValue("user_name");
  const role = getCookieValue("user_role");
  document.getElementById("userNameDisplay").textContent = name || "Unknown";
  document.getElementById("userRoleDisplay").textContent = (role || "Guest").toUpperCase();
}

function startSessionTimeout(minutes = 3) {
  let timeoutId;

  const resetTimer = () => {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(() => {
      Swal.fire("Session Expired", "You’ve been logged out due to inactivity.", "warning").then(() => {
        logout();
      });
    }, minutes * 60 * 1000);
  };

  // Activity listeners
  ['mousemove', 'mousedown', 'keydown', 'touchstart'].forEach(evt => {
    document.addEventListener(evt, resetTimer);
  });

  resetTimer();
}

function logout() {
  const expire = "expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
  document.cookie = "user_phone=;" + expire;
  document.cookie = "user_name=;" + expire;
  document.cookie = "user_role=;" + expire;
  location.reload();
}

function formatDateTime(datetimeStr) {
  const dt = new Date(datetimeStr);
  return dt.toLocaleString("en-US", { dateStyle: "short", timeStyle: "short" });
}

function generateGoogleCalendarLink({ title, start, end }) {
  const startUTC = new Date(start).toISOString().replace(/-|:|\.\d\d\d/g, "");
  const endUTC = new Date(end).toISOString().replace(/-|:|\.\d\d\d/g, "");
  return `https://www.google.com/calendar/render?action=TEMPLATE&text=${encodeURIComponent(title)}&dates=${startUTC}/${endUTC}`;
}

function launchLoginModal() {
  Swal.fire({
    title: "Login to Calendar",
    input: "tel",
    inputPlaceholder: "(###) ###-####",
    confirmButtonText: "Login",
    showCancelButton: false,
    allowOutsideClick: false,
    allowEscapeKey: false,
    didOpen: () => {
      const input = Swal.getInput();
      input.addEventListener("input", (e) => {
        let x = e.target.value.replace(/\D/g, '').substring(0, 10);
        const area = x.substring(0, 3);
        const mid = x.substring(3, 6);
        const last = x.substring(6, 10);
        if (x.length > 6) {
          e.target.value = `(${area}) ${mid}-${last}`;
        } else if (x.length > 3) {
          e.target.value = `(${area}) ${mid}`;
        } else if (x.length > 0) {
          e.target.value = `(${area}`;
        }
      });
    },
    preConfirm: async (value) => {
      const cleaned = value.replace(/\D/g, '');
      if (cleaned.length !== 10) {
        Swal.showValidationMessage("Enter a valid 10-digit phone number.");
        return false;
      }

      const formatted = `(${cleaned.slice(0, 3)}) ${cleaned.slice(3, 6)}-${cleaned.slice(6)}`;

      try {
        const res = await fetch(`login.php?phone=${encodeURIComponent(formatted)}`);
        const data = await res.json();

        if (data.success) {
  document.cookie = `user_phone=${encodeURIComponent(data.phone)}; path=/`;
  document.cookie = `user_name=${encodeURIComponent(data.name)}; path=/`;
  document.cookie = `user_role=${encodeURIComponent(data.role)}; path=/`;

  // ✅ NEW LINE — only for CFIs
  if (data.role === 'cfi' && data.cfi_id) {
    document.cookie = `user_cfi_id=${data.cfi_id}; path=/`;
  }

  localStorage.setItem("calendarFocusDate", new Date().toISOString());
  location.reload();
}
 else {
          Swal.showValidationMessage("Login failed.");
          return false;
        }
      } catch (err) {
        Swal.showValidationMessage("Login failed — connection error.");
        return false;
      }
    }
  });
}

window.launchLoginModal = launchLoginModal;


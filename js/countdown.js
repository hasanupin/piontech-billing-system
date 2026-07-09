(function () {
  var TARGET_ISO = "2026-08-06T00:00:00+07:00"; // launch: Aug 6, 2026, 00:00 WIB (Jakarta)
  var target = new Date(TARGET_ISO).getTime();

  var els = {
    days: document.getElementById("days"),
    hours: document.getElementById("hours"),
    minutes: document.getElementById("minutes"),
    seconds: document.getElementById("seconds"),
  };
  var targetNote = document.getElementById("targetNote");
  var countdownSection = document.querySelector(".countdown");
  var notifySection = document.getElementById("notifySection");
  var launchedBanner = document.getElementById("launchedBanner");

  function pad(n) {
    return String(n).padStart(2, "0");
  }

  function render() {
    var now = Date.now();
    var diff = target - now;

    if (diff <= 0) {
      els.days.textContent = "00";
      els.hours.textContent = "00";
      els.minutes.textContent = "00";
      els.seconds.textContent = "00";
      countdownSection.style.display = "none";
      targetNote.style.display = "none";
      notifySection.hidden = true;
      launchedBanner.hidden = false;
      clearInterval(timer);
      return;
    }

    var day = Math.floor(diff / 86400000);
    var hour = Math.floor((diff % 86400000) / 3600000);
    var min = Math.floor((diff % 3600000) / 60000);
    var sec = Math.floor((diff % 60000) / 1000);

    els.days.textContent = pad(day);
    els.hours.textContent = pad(hour);
    els.minutes.textContent = pad(min);
    els.seconds.textContent = pad(sec);
  }

  targetNote.textContent =
    "Launch: August 6, 2026 · 00:00 WIB (Jakarta) — shown above in your local time.";

  render();
  var timer = setInterval(render, 1000);

  document.getElementById("year").textContent = new Date().getFullYear();

  var form = document.getElementById("notifyForm");
  var msg = document.getElementById("notifyMsg");
  form.addEventListener("submit", function (e) {
    e.preventDefault();
    var email = document.getElementById("email").value.trim();
    if (!email) return;

    try {
      var stored = JSON.parse(localStorage.getItem("piontech_notify_list") || "[]");
      if (stored.indexOf(email) === -1) stored.push(email);
      localStorage.setItem("piontech_notify_list", JSON.stringify(stored));
    } catch (err) {
      /* localStorage unavailable, ignore */
    }

    form.reset();
    msg.textContent = "Thanks! We'll email you when we launch.";
  });
})();

const advancedBtn = document.querySelector(".advanced-btn");
advancedBtn &&
  advancedBtn.addEventListener("click", (event) => {
    event.stopPropagation();
    if (event.currentTarget.getAttribute("aria-expanded") === "false") {
      event.currentTarget.setAttribute("aria-expanded", "true");
      document.querySelector(".refinements-wrapper").style.display = "flex";
    } else {
      event.currentTarget.setAttribute("aria-expanded", "false");
      document.querySelector(".refinements-wrapper").style.display = "none";
    }
  });

const removeElement = (el) => {
  el.remove();
};

const announcement = (msg, selector, time) => {
  const el = document.querySelector(selector);
  el.innerHTML = msg;
  setTimeout(() => {
    el.innerHTML = "";
  }, time);
};

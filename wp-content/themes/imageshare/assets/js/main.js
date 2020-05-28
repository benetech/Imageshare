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

const filters = document.querySelector(".current-filters");
filters &&
  filters.addEventListener(
    "click",
    (event) => {
      let target;
      event.stopPropagation();
      if (event.target.nodeName === "SPAN") {
        target = event.target.parentElement;
      } else if (event.target.nodeName === "BUTTON") {
        target = event.target;
      }
      const label =
        "Filter dismissed: " +
        target.parentElement.querySelector(".filter-txt").innerText.trim();
      try {
        if (document.querySelectorAll(".filter-btn").length === 1) {
          removeElement(target.parentElement);
          document.getElementById("no-filters").style.display = "inline-block";
          document.getElementById("filters-heading").focus();
          filters.remove();
          announcement(label, "#announcement", 1000);
          return;
        }
      } catch (error) {}

      try {
        target.parentElement.nextElementSibling.firstElementChild.focus();
        removeElement(target.parentElement);
        announcement(label, "#announcement", 1000);
        return;
      } catch (error) {}

      try {
        target.parentElement.previousElementSibling.firstElementChild.focus();
        removeElement(target.parentElement);
        announcement(label, "#announcement", 1000);
        return;
      } catch (error) {}
    },
    false
  );

document.querySelectorAll(".tab").forEach((tab, index, arr) => {
  tab.addEventListener("click", (e) => {
    const i = index;
    resetTabs(arr);
    selectTab(i, arr);
  });
  tab.addEventListener("keydown", (e) => {
    const i = index;

    if (e.keyCode === 36) {
      arr[0].focus();
    }

    if (e.keyCode == 35) {
      arr[arr.length - 1].focus();
    }

    if (e.keyCode === 37) {
      // move backward
      resetTabs(arr);
      selectTab(i > 0 ? i - 1 : arr.length - 1, arr);
    }

    if (e.keyCode === 39) {
      // move forward
      resetTabs(arr);
      selectTab(i < arr.length - 1 ? i + 1 : 0, arr);
    }
  });
});

const selectTab = (index, arr) => {
  arr[index].setAttribute("aria-selected", true);
  arr[index].removeAttribute("tabindex");
  arr[index].focus();
  document
    .getElementById(arr[index].getAttribute("aria-controls"))
    .removeAttribute("hidden");
};

const resetTabs = (tabs) => {
  tabs.forEach((tab) => {
    document
      .getElementById(tab.getAttribute("aria-controls"))
      .setAttribute("hidden", "hidden");
    tab.setAttribute("tabindex", -1);
    tab.setAttribute("aria-selected", false);
  });
};

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

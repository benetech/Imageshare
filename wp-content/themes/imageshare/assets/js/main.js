var advancedBtn = document.querySelector(".advanced-btn");
advancedBtn &&
  advancedBtn.addEventListener("click", function (event) {
    event.stopPropagation();
    if (event.currentTarget.getAttribute("aria-expanded") === "false") {
      event.currentTarget.parentNode.classList.add('expanded');
      event.currentTarget.setAttribute("aria-expanded", "true");
      document.querySelector(".refinements-wrapper").style.display = "flex";
    } else {
      event.currentTarget.parentNode.classList.remove('expanded');
      event.currentTarget.setAttribute("aria-expanded", "false");
      document.querySelector(".refinements-wrapper").style.display = "none";
    }
  });

var removeElement = function (el) {
  el.remove();
};

var announcement = function (msg, selector, time) {
  var el = document.querySelector(selector);
  el.innerHTML = msg;
  setTimeout(function () {
    el.innerHTML = "";
  }, time);
};

if (resetBtn = document.querySelector('button#reset')) {
  resetBtn.removeAttribute('hidden');
  resetBtn.addEventListener("click", function (event) {
    document.getElementById('search').value = '';
    if (filters = document.querySelector('#search-filters')) {
        filters.remove();
    }
    document.querySelector('#subject').selectedIndex = 0;
    document.querySelector('#type').selectedIndex = 0;
    document.querySelector('#acc').selectedIndex = 0;
    announcement('Search form has been reset.', '#announcement', 3000);
  });
}

var navigateImages = document.querySelectorAll('img[data-click-navigate]');
for (var i = 0, j = navigateImages.length; i < j; i++) {
    var image = navigateImages[i];
    image.addEventListener('click', function () {
        document.location.href = this.dataset.clickNavigate;    
    }.bind(image));
}

var fileGroupToggleButtons = document.querySelectorAll('button.file-group-toggle');
for (var i = 0, j = fileGroupToggleButtons.length; i < j; i++) {
    var toggleButton = fileGroupToggleButtons[i];
    toggleButton.addEventListener('click', function () {
        var isExpanded = this.getAttribute('aria-expanded') === 'true';
        this.setAttribute('aria-expanded', !isExpanded);
    }.bind(toggleButton));
}

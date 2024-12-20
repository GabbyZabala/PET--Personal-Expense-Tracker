function showTab(tabId) {
  const tabs = document.querySelectorAll(".tab-content");
  const buttons = document.querySelectorAll(".tab-button");

  tabs.forEach((tab) => tab.classList.remove("active"));
  buttons.forEach((button) => button.classList.remove("active"));

  document.getElementById(tabId).classList.add("active");
  document
    .querySelector(`button[onclick="showTab('${tabId}')"]`)
    .classList.add("active");
}

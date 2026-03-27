export function saveOfflineAction(action) {
  let actions = JSON.parse(localStorage.getItem("offline_actions")) || [];
  actions.push(action);
  localStorage.setItem("offline_actions", JSON.stringify(actions));
}

export function getOfflineActions() {
  return JSON.parse(localStorage.getItem("offline_actions")) || [];
}

export function clearOfflineActions() {
  localStorage.removeItem("offline_actions");
}
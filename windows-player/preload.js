const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('signage', {
  getConfig:      ()       => ipcRenderer.invoke('get-config'),
  saveAndLaunch:  (cfg)    => ipcRenderer.invoke('save-and-launch', cfg),
  saveSettings:   (s)      => ipcRenderer.invoke('save-settings', s),
  openSetup:      ()       => ipcRenderer.invoke('open-setup'),
  ping:           (url)    => ipcRenderer.invoke('ping', url),
  quit:           ()       => ipcRenderer.invoke('quit'),
});

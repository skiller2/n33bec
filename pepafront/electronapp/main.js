const { app, BrowserWindow, session } = require('electron');
//const url = "http://10.100.1.199/#!/displaygeneral"
const url = "http://10.100.1.104/#!/displaygeneral"
if (process.platform === 'linux') {
  app.commandLine.appendSwitch('enable-features', 'VaapiVideoDecoder')
  // This switch is not really for video decoding I enabled for smoother UI
  app.commandLine.appendSwitch('enable-gpu-rasterization')

}

app.on('ready', async function () {
  await session.defaultSession.clearCache();
  const mainWindow = new BrowserWindow({
    show: false,
    frame: false,
    width: 1920, height: 1080
  });
  mainWindow.maximize();
  mainWindow.show();  
  try {
    await mainWindow.loadURL(url)
  } catch (error) {
    app.exit()
  }
});

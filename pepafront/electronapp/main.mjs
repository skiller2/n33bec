import { app, BrowserWindow,session } from 'electron';
import { exit } from 'process';
//const url = 'https://siderca.efaisa.com.ar'
const url = 'http://10.100.1.199/#!/displaygeneral'
//const url = 'http://localhost:9000'


app.on('ready', async function () {
  await session.defaultSession.clearCache();

  const mainWindow = new BrowserWindow({
    show: false,
    frame: false
  });
  mainWindow.maximize();
  mainWindow.show();
  
  try {
    await mainWindow.loadURL(url, { "extraHeaders": "pragma: no-cache\n" })
  } catch (error){
    console.error('Error loading URL:', error);
    exit()
  }

//  mainWindow.openDevTools();
});

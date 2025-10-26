#!/usr/bin/env node
// Simple OBS WebSocket CLI controller
// Usage: obs-ctl <command> [args...]

const OBSWebSocket = require('obs-websocket-js').default;

const obs = new OBSWebSocket();

const OBS_HOST = process.env.OBS_HOST || 'localhost';
const OBS_PORT = process.env.OBS_PORT || '4455';
const OBS_PASSWORD = process.env.OBS_PASSWORD;

if (!OBS_PASSWORD) {
  console.error('Error: OBS_PASSWORD environment variable not set');
  process.exit(1);
}

async function connect() {
  try {
    await obs.connect(`ws://${OBS_HOST}:${OBS_PORT}`, OBS_PASSWORD);
  } catch (error) {
    console.error('Error: Cannot connect to OBS WebSocket');
    console.error('Make sure OBS is running and WebSocket is enabled');
    process.exit(1);
  }
}

async function disconnect() {
  await obs.disconnect();
}

async function main() {
  const command = process.argv[2];
  const args = process.argv.slice(3);

  await connect();

  try {
    switch (command) {
      case 'recording-start':
        await obs.call('StartRecord');
        console.log('Recording started');
        break;

      case 'recording-stop':
        const stopResult = await obs.call('StopRecord');
        console.log('Recording stopped');
        if (stopResult.outputPath) {
          console.log(`outputPath: ${stopResult.outputPath}`);
        }
        break;

      case 'recording-status':
        const recordStatus = await obs.call('GetRecordStatus');
        console.log(JSON.stringify(recordStatus, null, 2));
        break;

      case 'scene-current':
        const { currentProgramSceneName } = await obs.call('GetCurrentProgramScene');
        console.log(currentProgramSceneName);
        break;

      case 'scene-list':
        const { scenes } = await obs.call('GetSceneList');
        scenes.forEach(scene => console.log(scene.sceneName));
        break;

      case 'scene-switch':
        if (!args[0]) {
          console.error('Usage: obs-ctl scene-switch <scene-name>');
          process.exit(1);
        }
        await obs.call('SetCurrentProgramScene', { sceneName: args[0] });
        console.log(`Switched to scene: ${args[0]}`);
        break;

      case 'version':
        const version = await obs.call('GetVersion');
        console.log(`OBS Studio: ${version.obsVersion}`);
        console.log(`WebSocket: ${version.obsWebSocketVersion}`);
        break;

      default:
        console.log(`
OBS Control - Simple CLI for OBS Studio WebSocket

Usage: obs-ctl <command> [args...]

Commands:
  recording-start          Start recording
  recording-stop           Stop recording (returns output path)
  recording-status         Get recording status
  scene-current            Get current scene name
  scene-list               List all scenes
  scene-switch <name>      Switch to scene
  version                  Show OBS version info

Environment:
  OBS_PASSWORD=<password>  Required
  OBS_HOST=localhost       Optional (default: localhost)
  OBS_PORT=4455            Optional (default: 4455)

Examples:
  export OBS_PASSWORD="your-password"
  obs-ctl scene-current
  obs-ctl recording-start
  obs-ctl recording-stop
`);
        process.exit(command ? 1 : 0);
    }
  } catch (error) {
    console.error(`Error: ${error.message}`);
    if (error.code) {
      console.error(`Code: ${error.code}`);
    }
    if (error.comment) {
      console.error(`Comment: ${error.comment}`);
    }
    process.exit(1);
  }

  await disconnect();
}

main().catch(error => {
  console.error(`Fatal error: ${error.message}`);
  process.exit(1);
});

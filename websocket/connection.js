const logger = require('./logger')
const channels = require('./channels')

function handleConnection(ws, req) {
    ws._channels = new Set()

    logger.info({ ip: req.socket.remoteAddress }, 'Client connected')

    ws.on('message', (data) => handleMessage(ws, data))
    ws.on('close', () => handleClose(ws))
    ws.on('error', (err) => logger.error({ err }, 'WebSocket error'))
}

function handleMessage(ws, data) {
    let msg

    try {
        msg = JSON.parse(data.toString())
    } catch (err) {
        logger.warn({ err }, 'Failed to parse client message')
        return
    }

    if (msg.type === 'subscribe' && msg.channel) {
        channels.subscribe(msg.channel, ws)
    } else if (msg.type === 'unsubscribe' && msg.channel) {
        channels.unsubscribe(msg.channel, ws)
    }
}

function handleClose(ws) {
    channels.unsubscribeAll(ws)
    logger.info('Client disconnected')
}

module.exports = { handleConnection }

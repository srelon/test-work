const { WebSocketServer } = require('ws')
const logger = require('./logger')
const { handleConnection } = require('./connection')

const PORT = process.env.WS_PORT || 6001

const wss = new WebSocketServer({ port: PORT })

logger.info(`WebSocket server started on port ${PORT}`)

wss.on('connection', handleConnection)

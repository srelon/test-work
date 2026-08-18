const redis = require('../redis')
const logger = require('../logger')

const subscribers = new Map()

function subscribe(channel, ws) {
    let sockets = subscribers.get(channel)

    if (!sockets) {
        sockets = new Set()
        subscribers.set(channel, sockets)
        redis.subscribe(channel, (err) => {
            if (err) logger.error({ err, channel }, 'Failed to subscribe to channel')
            else logger.info({ channel }, 'Subscribed to channel')
        })
    }

    sockets.add(ws)
    ws._channels.add(channel)
}

function unsubscribe(channel, ws) {
    const sockets = subscribers.get(channel)

    if (!sockets) return

    sockets.delete(ws)
    ws._channels.delete(channel)

    if (sockets.size === 0) {
        subscribers.delete(channel)
        redis.unsubscribe(channel, (err) => {
            if (!err) logger.info({ channel }, 'Unsubscribed from channel')
        })
    }
}

function unsubscribeAll(ws) {
    for (const channel of ws._channels) {
        unsubscribe(channel, ws)
    }
}

function relay(channel, message) {
    const sockets = subscribers.get(channel)

    if (!sockets || sockets.size === 0) return

    for (const ws of sockets) {
        if (ws.readyState === ws.OPEN) {
            ws.send(message)
        }
    }
}

redis.on('message', (channel, message) => {
    relay(channel, message)
})

module.exports = {
    subscribe,
    unsubscribe,
    unsubscribeAll,
}

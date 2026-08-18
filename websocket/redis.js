const Redis = require('ioredis')
const logger = require('./logger')

const redis = new Redis({
    host: process.env.REDIS_HOST || 'redis',
    port: process.env.REDIS_PORT || 6379,
})

redis.on('connect', () => logger.info('Connected to Redis'))
redis.on('error', (err) => logger.error({ err }, 'Redis error'))

module.exports = redis

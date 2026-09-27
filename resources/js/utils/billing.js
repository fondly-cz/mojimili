export const formatCurrency = (value) => new Intl.NumberFormat('cs-CZ', {
    style: 'currency',
    currency: 'CZK',
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
}).format(value || 0)

export const formatMinutes = (minutes) => {
    const total = Number(minutes) || 0
    const hours = Math.floor(total / 60)
    const rest = total % 60

    if (hours === 0) return `${rest} min`
    return rest === 0 ? `${hours} h` : `${hours} h ${rest} min`
}

export const formatDate = (value) => value
    ? new Date(value).toLocaleDateString('cs-CZ', { day: 'numeric', month: 'numeric', year: 'numeric' })
    : '—'

/**
 * Accepts "90", "1:30", "1.5" or "1,5 h" and returns minutes (null when unparseable).
 */
export const parseDuration = (input) => {
    const value = String(input ?? '').trim().toLowerCase().replace(/\s*h$/, '')

    if (value === '') return null

    const clock = value.match(/^(\d+):(\d{1,2})$/)
    if (clock) return Number(clock[1]) * 60 + Number(clock[2])

    if (/^\d+$/.test(value)) return Number(value)

    const decimal = value.replace(',', '.')
    if (/^\d*\.\d+$/.test(decimal)) return Math.round(Number(decimal) * 60)

    return null
}

const toMinutes = (time) => {
    const [hours, minutes] = String(time).split(':').map(Number)
    return hours * 60 + minutes
}

/**
 * Minutes from "HH:MM" to "HH:MM"; an end not after the start counts as the next day.
 */
export const minutesBetween = (from, to) => {
    if (!from || !to) return null

    const diff = toMinutes(to) - toMinutes(from)
    return diff > 0 ? diff : diff + 24 * 60
}

export const addMinutesToTime = (time, minutes) => {
    const total = (toMinutes(time) + minutes) % (24 * 60)
    return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`
}

export const formatClock = (minutes) => `${Math.floor(minutes / 60)}:${String(minutes % 60).padStart(2, '0')}`

export const formatTimeRange = (report) => report.started_at && report.ended_at
    ? `${report.started_at.substring(11, 16)}–${report.ended_at.substring(11, 16)}`
    : ''

export const reportAmount = (report) => Math.round((report.minutes / 60) * Number(report.hourly_rate) * 100) / 100

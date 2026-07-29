import { useCallback, useEffect, useRef, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'
import MarketplaceReportButton from '../components/MarketplaceReportButton'

const initials = (name) => name?.split(' ').map((part) => part[0]).slice(0, 2).join('') || 'TX'
const acceptedFiles = '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.txt,.csv,.jpg,.jpeg,.png,.webp'

function AttachmentIcon() {
  return <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="m21.4 11.6-8.8 8.8a6 6 0 0 1-8.5-8.5l9.2-9.2a4 4 0 0 1 5.7 5.7L9.7 17.7a2 2 0 1 1-2.8-2.8l8.5-8.5" /></svg>
}

function ContactAvatar({ user }) {
  return <span className="message-avatar">{user?.profile_photo_url ? <img src={user.profile_photo_url} alt="" /> : initials(user?.name)}</span>
}

function formatBytes(size) {
  if (!size) return ''
  if (size < 1024 * 1024) return `${Math.ceil(size / 1024)} KB`
  return `${(size / (1024 * 1024)).toFixed(1)} MB`
}

export default function MessagesScreen() {
  const { user, errorMessage } = useAuth()
  const [params, setParams] = useSearchParams()
  const [conversations, setConversations] = useState([])
  const [conversation, setConversation] = useState(null)
  const [startable, setStartable] = useState([])
  const [message, setMessage] = useState('')
  const [attachments, setAttachments] = useState([])
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [updatedAt, setUpdatedAt] = useState(null)
  const [showJumpToLatest, setShowJumpToLatest] = useState(false)
  const selectedId = params.get('conversation')
  const isClient = user?.roles?.includes('client')
  const messagesContainer = useRef(null)
  const uploadInput = useRef(null)
  const stayAtLatest = useRef(true)
  const previousConversationId = useRef(null)

  const loadConversation = useCallback(async (conversationId, updateUrl = true) => {
    try {
      if (String(conversationId) !== String(conversation?.id)) stayAtLatest.current = true
      const { data } = await api.get(`/conversations/${conversationId}`)
      setConversation(data.data)
      setUpdatedAt(new Date())
      if (updateUrl && String(conversationId) !== String(selectedId)) setParams({ conversation: conversationId })
    } catch (requestError) { setError(errorMessage(requestError)) }
  }, [conversation?.id, errorMessage, selectedId, setParams])

  const loadConversations = useCallback(async (preferredId = selectedId, updateUrl = true) => {
    try {
      const { data } = await api.get('/conversations')
      setConversations(data.data)
      const nextId = preferredId || data.data[0]?.id
      if (nextId) await loadConversation(nextId, updateUrl)
    } catch (requestError) { setError(errorMessage(requestError)) }
  }, [errorMessage, loadConversation, selectedId])

  useEffect(() => { if (user?.id) loadConversations() }, [loadConversations, user?.id])
  useEffect(() => {
    if (!isClient) return
    api.get('/conversations/startable-proposals').then(({ data }) => setStartable(data.data)).catch(() => setStartable([]))
  }, [isClient, user?.id])
  useEffect(() => {
    if (!user?.id) return undefined
    const interval = window.setInterval(() => {
      const activeId = selectedId || conversation?.id
      loadConversations(activeId, false).catch(() => {})
    }, 5000)
    return () => window.clearInterval(interval)
  }, [conversation?.id, loadConversations, selectedId, user?.id])
  useEffect(() => {
    const changedConversation = String(previousConversationId.current) !== String(conversation?.id)
    if (changedConversation) stayAtLatest.current = true
    previousConversationId.current = conversation?.id
    if (stayAtLatest.current && messagesContainer.current) {
      messagesContainer.current.scrollTop = messagesContainer.current.scrollHeight
      setShowJumpToLatest(false)
    }
  }, [conversation?.id, conversation?.messages?.length])

  const trackScroll = () => {
    const element = messagesContainer.current
    if (!element) return
    const atLatest = element.scrollHeight - element.scrollTop - element.clientHeight < 72
    stayAtLatest.current = atLatest
    setShowJumpToLatest(!atLatest)
  }

  const jumpToLatest = () => {
    if (!messagesContainer.current) return
    messagesContainer.current.scrollTo({ top: messagesContainer.current.scrollHeight, behavior: 'smooth' })
    stayAtLatest.current = true
    setShowJumpToLatest(false)
  }

  const startConversation = async (proposal) => {
    setBusy(true); setError('')
    try {
      const { data } = await api.post(`/proposals/${proposal.id}/conversation`)
      await loadConversations(data.data.id)
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }

  const chooseAttachments = (event) => {
    const selected = Array.from(event.target.files || [])
    if (selected.length > 5 || selected.some((file) => file.size > 20 * 1024 * 1024)) {
      setError('Attach up to five supported files, each no larger than 20 MB.')
      event.target.value = ''
      return
    }
    setAttachments(selected)
    setError('')
  }

  const removeAttachment = (index) => {
    setAttachments((files) => files.filter((_, itemIndex) => itemIndex !== index))
    if (uploadInput.current) uploadInput.current.value = ''
  }

  const send = async (event) => {
    event.preventDefault()
    if ((!message.trim() && !attachments.length) || !conversation) return
    stayAtLatest.current = true
    setBusy(true); setError('')
    try {
      const payload = new FormData()
      if (message.trim()) payload.append('body', message.trim())
      attachments.forEach((file) => payload.append('files[]', file))
      await api.post(`/conversations/${conversation.id}/messages`, payload)
      setMessage('')
      setAttachments([])
      if (uploadInput.current) uploadInput.current.value = ''
      await loadConversations(conversation.id)
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }

  const downloadAttachment = async (file) => {
    try {
      const response = await api.get(`/conversation-message-files/${file.id}/download`, { responseType: 'blob' })
      const url = window.URL.createObjectURL(new Blob([response.data], { type: file.mime_type }))
      const link = document.createElement('a')
      link.href = url
      link.download = file.original_name
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(url)
    } catch (requestError) { setError(errorMessage(requestError)) }
  }

  return <section className="messages-page">
    <header><div><p className="eyebrow">Messages</p><h1>Keep work conversations in one place.</h1><p>Messages update automatically while this page is open. Share project context and supported files directly with the right person.</p></div></header>
    {error && <p className="form-notice" role="alert">{error}</p>}
    <div className="messages-layout">
      <aside className="conversation-list" aria-label="Conversations">
        <div className="conversation-list-title"><h2>Conversations</h2><span>{conversations.reduce((total, item) => total + item.unread_count, 0)} unread</span></div>
        {conversations.length ? conversations.map((item) => <button className={String(item.id) === String(conversation?.id) ? 'selected' : ''} key={item.id} onClick={() => loadConversation(item.id)}><ContactAvatar user={item.other_user} /><div><b>{item.other_user?.name}</b><small>{item.job?.title}</small><p>{item.last_message?.body || (item.type === 'project' ? 'Project chat is ready.' : 'Conversation started.')}</p></div>{item.unread_count > 0 && <em>{item.unread_count}</em>}</button>) : <p className="empty-panel">No conversations yet.</p>}
        {isClient && startable.length > 0 && <div className="startable-list"><h3>Start from a proposal</h3>{startable.map((proposal) => <button key={proposal.id} disabled={busy} onClick={() => startConversation(proposal)}><ContactAvatar user={proposal.freelancer} /><div><b>{proposal.freelancer?.name}</b><small>{proposal.job?.title}</small></div><span>Message</span></button>)}</div>}
      </aside>
      <main className="chat-panel">
        {conversation ? <>
          <header className="chat-header"><ContactAvatar user={conversation.other_user} /><div><b>{conversation.other_user?.name}</b><small>{conversation.type === 'project' ? `Project chat · ${conversation.job?.title}` : `Proposal chat · ${conversation.job?.title}`}</small></div><span className="chat-sync" aria-live="polite">{updatedAt ? 'Auto-updated' : 'Loading…'}</span></header>
          <div className="chat-messages" ref={messagesContainer} onScroll={trackScroll} aria-live="polite">
            {showJumpToLatest && <button type="button" className="jump-to-latest" onClick={jumpToLatest}>Jump to latest message</button>}
            {conversation.messages?.map((item) => item.kind === 'system'
              ? <p className="chat-system-event" key={item.id}>{item.body}<small>{new Date(item.created_at).toLocaleString()}</small></p>
              : <article className={item.sender_id === user.id ? 'sent' : ''} key={item.id}><p>{item.body}</p>{item.files?.length > 0 && <div className="message-files">{item.files.map((file) => <button type="button" key={file.id} onClick={() => downloadAttachment(file)}><AttachmentIcon /><span><b>{file.original_name}</b><small>{formatBytes(file.file_size)}</small></span></button>)}</div>}<small>{new Date(item.created_at).toLocaleString()}</small>{item.sender_id !== user.id && <MarketplaceReportButton targetType="message" targetId={item.id} />}</article>)}
          </div>
          <form className="chat-compose" onSubmit={send}>
            {attachments.length > 0 && <div className="chat-attachment-list">{attachments.map((file, index) => <span key={`${file.name}-${file.lastModified}`}><AttachmentIcon />{file.name} <button type="button" onClick={() => removeAttachment(index)} aria-label={`Remove ${file.name}`}>×</button></span>)}</div>}
            <div className="chat-compose-row"><textarea value={message} onChange={(event) => setMessage(event.target.value)} maxLength="4000" placeholder="Write a message…" aria-label="Message" /><label className="chat-attach" title="Attach files"><AttachmentIcon /><span>Attach</span><input ref={uploadInput} type="file" multiple accept={acceptedFiles} onChange={chooseAttachments} /></label><button disabled={busy || (!message.trim() && !attachments.length)} className="button button-primary">{busy ? 'Sending…' : 'Send'}</button></div><small className="chat-file-help">Up to 5 files · PDF, Office files, ZIP, text, CSV, or images · 20 MB each</small>
          </form>
        </> : <div className="chat-empty"><h2>Select a conversation</h2><p>Clients can start a chat with an active proposal. Freelancers can reply once invited.</p></div>}
      </main>
    </div>
  </section>
}

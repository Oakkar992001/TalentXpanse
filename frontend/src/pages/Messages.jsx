import { useEffect, useRef, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'

const initials = (name) => name?.split(' ').map((part) => part[0]).slice(0, 2).join('') || 'TX'

function ContactAvatar({ user }) {
  return <span className="message-avatar">{user?.profile_photo_url ? <img src={user.profile_photo_url} alt="" /> : initials(user?.name)}</span>
}

export default function MessagesScreen() {
  const { user, errorMessage } = useAuth()
  const [params, setParams] = useSearchParams()
  const [conversations, setConversations] = useState([])
  const [conversation, setConversation] = useState(null)
  const [startable, setStartable] = useState([])
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const selectedId = params.get('conversation')
  const messageEnd = useRef(null)

  const loadConversations = async () => {
    try {
      const { data } = await api.get('/conversations')
      setConversations(data.data)
      const nextId = selectedId || data.data[0]?.id
      if (nextId) loadConversation(nextId)
    } catch (requestError) { setError(errorMessage(requestError)) }
  }

  const loadConversation = async (conversationId) => {
    try {
      const { data } = await api.get(`/conversations/${conversationId}`)
      setConversation(data.data)
      if (String(conversationId) !== selectedId) setParams({ conversation: conversationId })
    } catch (requestError) { setError(errorMessage(requestError)) }
  }

  useEffect(() => { if (user) loadConversations() }, [user?.id])
  useEffect(() => { if (user?.roles?.includes('client')) api.get('/conversations/startable-proposals').then(({ data }) => setStartable(data.data)).catch(() => setStartable([])) }, [user?.id])
  useEffect(() => {
    if (!user) return undefined
    const poll = async () => {
      try {
        const { data } = await api.get('/conversations')
        setConversations(data.data)
        const activeId = selectedId || data.data[0]?.id
        if (activeId) {
          const response = await api.get(`/conversations/${activeId}`)
          setConversation(response.data.data)
          if (!selectedId) setParams({ conversation: activeId })
        }
      } catch { /* Keep the existing chat visible if a background refresh fails. */ }
    }
    const interval = window.setInterval(poll, 4000)
    return () => window.clearInterval(interval)
  }, [user?.id, selectedId])
  useEffect(() => { messageEnd.current?.scrollIntoView({ block: 'end' }) }, [conversation?.messages?.length])

  const startConversation = async (proposal) => {
    setBusy(true); setError('')
    try {
      const { data } = await api.post(`/proposals/${proposal.id}/conversation`)
      await loadConversations(); await loadConversation(data.data.id)
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }

  const send = async (event) => {
    event.preventDefault()
    if (!message.trim() || !conversation) return
    setBusy(true); setError('')
    try {
      await api.post(`/conversations/${conversation.id}/messages`, { body: message.trim() })
      setMessage(''); await loadConversations(); await loadConversation(conversation.id)
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }

  return <section className="messages-page"><header><div><p className="eyebrow">Messages</p><h1>Keep work conversations in one place.</h1><p>Text-only chat is available after a client opens a proposal conversation. Hiring turns it into a project chat.</p></div></header>{error && <p className="form-notice">{error}</p>}<div className="messages-layout"><aside className="conversation-list"><div className="conversation-list-title"><h2>Conversations</h2><span>{conversations.reduce((total, item) => total + item.unread_count, 0)} unread</span></div>{conversations.length ? conversations.map((item) => <button className={String(item.id) === String(conversation?.id) ? 'selected' : ''} key={item.id} onClick={() => loadConversation(item.id)}><ContactAvatar user={item.other_user} /><div><b>{item.other_user?.name}</b><small>{item.job?.title}</small><p>{item.last_message?.body || (item.type === 'project' ? 'Project chat is ready.' : 'Conversation started.')}</p></div>{item.unread_count > 0 && <em>{item.unread_count}</em>}</button>) : <p className="empty-panel">No conversations yet.</p>}{user?.roles?.includes('client') && startable.length > 0 && <div className="startable-list"><h3>Start from a proposal</h3>{startable.map((proposal) => <button key={proposal.id} disabled={busy} onClick={() => startConversation(proposal)}><ContactAvatar user={proposal.freelancer} /><div><b>{proposal.freelancer?.name}</b><small>{proposal.job?.title}</small></div><span>Message</span></button>)}</div>}</aside><main className="chat-panel">{conversation ? <><header className="chat-header"><ContactAvatar user={conversation.other_user} /><div><b>{conversation.other_user?.name}</b><small>{conversation.type === 'project' ? `Project chat · ${conversation.job?.title}` : `Proposal chat · ${conversation.job?.title}`}</small></div></header><div className="chat-messages">{conversation.messages?.map((item) => <article className={item.sender_id === user.id ? 'sent' : ''} key={item.id}><p>{item.body}</p><small>{new Date(item.created_at).toLocaleString()}</small></article>)}<div ref={messageEnd} /></div><form className="chat-compose" onSubmit={send}><textarea value={message} onChange={(event) => setMessage(event.target.value)} maxLength="4000" placeholder="Write a message…" /><button disabled={busy || !message.trim()} className="button button-primary">Send</button></form></> : <div className="chat-empty"><h2>Select a conversation</h2><p>Clients can start a chat with an active proposal. Freelancers can reply once invited.</p></div>}</main></div></section>
}

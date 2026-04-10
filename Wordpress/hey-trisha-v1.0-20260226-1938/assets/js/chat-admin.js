document.addEventListener("DOMContentLoaded", function () {
    const root = document.getElementById("heytrisha-chat-admin-root");
    if (!root) return;

    const config = window.heytrishaChatConfig || {};
    // Destructure both REST nonce and chatbot (admin-ajax) nonce
    const { pluginUrl, ajaxurl, chatId, restUrl, nonce, chatbotNonce } = config;

    const ChatAdmin = () => {
        const [chats, setChats] = React.useState([]);
        const [currentChat, setCurrentChat] = React.useState(null);
        const [messages, setMessages] = React.useState([]);
        const [inputText, setInputText] = React.useState("");
        const [isLoading, setIsLoading] = React.useState(false);
        const [isTyping, setIsTyping] = React.useState(false);
        const [sidebarOpen, setSidebarOpen] = React.useState(true);
        const messagesEndRef = React.useRef(null);
        const inputRef = React.useRef(null);

        // Load chats on mount
        React.useEffect(() => {
            loadChats();
            // ✅ wp_localize_script sends 0 as string "0", which is truthy in JS
            const parsedChatId = parseInt(chatId, 10);
            if (parsedChatId > 0) {
                loadChat(parsedChatId);
            }
        }, []);

        // Auto-scroll to bottom
        React.useEffect(() => {
            messagesEndRef.current?.scrollIntoView({ behavior: "smooth" });
        }, [messages, isTyping]);

        // Load chats list
        const loadChats = async () => {
            try {
                const response = await fetch(`${restUrl}chats?archived=false`, {
                    headers: {
                        'X-WP-Nonce': nonce
                    }
                });
                if (!response.ok) {
                    console.error('Failed to load chats: HTTP', response.status);
                    setChats([]);
                    return;
                }
                const data = await response.json();
                setChats(Array.isArray(data) ? data : []);
            } catch (error) {
                console.error('Failed to load chats:', error);
            }
        };

        // Load single chat with messages
        const loadChat = async (id) => {
            if (!id || parseInt(id, 10) <= 0) return;
            try {
                setIsLoading(true);
                const response = await fetch(`${restUrl}chats/${id}`, {
                    headers: {
                        'X-WP-Nonce': nonce
                    }
                });
                if (!response.ok) {
                    console.error('Failed to load chat: HTTP', response.status);
                    setIsLoading(false);
                    return;
                }
                const chat = await response.json();
                if (chat && !chat.code) {
                    setCurrentChat(chat);
                    // Process messages to extract formattedData from metadata
                    const processedMessages = (chat.messages || []).map(msg => {
                        const processedMsg = { ...msg };
                        
                        // Parse metadata if it's a JSON string
                        let parsedMetadata = null;
                        if (msg.metadata) {
                            try {
                                if (typeof msg.metadata === 'string') {
                                    parsedMetadata = JSON.parse(msg.metadata);
                                } else if (typeof msg.metadata === 'object') {
                                    parsedMetadata = msg.metadata;
                                }
                            } catch (e) {
                                console.warn('Failed to parse metadata:', e);
                                parsedMetadata = null;
                            }
                        }
                        
                        // Extract formattedData from parsed metadata
                        if (parsedMetadata && parsedMetadata.formattedData) {
                            // formattedData might also be a JSON string
                            if (typeof parsedMetadata.formattedData === 'string') {
                                try {
                                    processedMsg.formattedData = JSON.parse(parsedMetadata.formattedData);
                                } catch (e) {
                                    console.warn('Failed to parse formattedData:', e);
                                    processedMsg.formattedData = parsedMetadata.formattedData;
                                }
                            } else {
                                processedMsg.formattedData = parsedMetadata.formattedData;
                            }
                        } else if (parsedMetadata && parsedMetadata.data) {
                            // If metadata has data but no formattedData, reconstruct formattedData
                            const data = Array.isArray(parsedMetadata.data) ? parsedMetadata.data : [parsedMetadata.data];
                            if (data.length > 0) {
                                processedMsg.formattedData = {
                                    type: "table",
                                    content: data,
                                    summary: `Found ${data.length} result${data.length > 1 ? 's' : ''}`
                                };
                            }
                        }
                        
                        // If content contains JSON, try to extract it (fallback)
                        if (!processedMsg.formattedData && msg.content && msg.content.includes('{')) {
                            try {
                                const jsonMatch = msg.content.match(/\{[\s\S]*\}$/);
                                if (jsonMatch) {
                                    const parsed = JSON.parse(jsonMatch[0]);
                                    if (parsed.type && (parsed.type === 'table' || parsed.type === 'details' || parsed.type === 'card')) {
                                        processedMsg.formattedData = parsed;
                                        // Remove JSON from text
                                        processedMsg.content = msg.content.replace(/\s*\{[\s\S]*\}$/, '').trim();
                                    }
                                }
                            } catch (e) {
                                // Ignore parsing errors
                            }
                        }
                        return processedMsg;
                    });
                    setMessages(processedMessages);
                }
            } catch (error) {
                console.error('Failed to load chat:', error);
            } finally {
                setIsLoading(false);
            }
        };

        // Create new chat
        const createNewChat = async () => {
            try {
                const response = await fetch(`${restUrl}chats`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': nonce
                    },
                    body: JSON.stringify({ title: 'New Chat' })
                });
                const chat = await response.json();
                if (chat && !chat.code) {
                    setCurrentChat(chat);
                    setMessages([]);
                    loadChats();
                    // Update URL
                    window.history.pushState({}, '', `?page=heytrisha-new-chat&chat_id=${chat.id}`);
                }
            } catch (error) {
                console.error('Failed to create chat:', error);
            }
        };

        // Send message
        const sendMessage = async () => {
            if (!inputText.trim() || isTyping) return;

            const userMessage = inputText.trim();
            setInputText("");
            
            // If no current chat, create one
            let chat = currentChat;
            if (!chat) {
                const createResponse = await fetch(`${restUrl}chats`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': nonce
                    },
                    body: JSON.stringify({ title: userMessage.substring(0, 50) })
                });
                chat = await createResponse.json();
                if (chat && !chat.code) {
                    setCurrentChat(chat);
                    window.history.pushState({}, '', `?page=heytrisha-new-chat&chat_id=${chat.id}`);
                    loadChats();
                } else {
                    alert('Failed to create chat');
                    return;
                }
            }

            // Add user message
            const userMsgResponse = await fetch(`${restUrl}chats/${chat.id}/messages`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: JSON.stringify({
                    role: 'user',
                    content: userMessage
                })
            });
            const userMsg = await userMsgResponse.json();
            if (userMsg && !userMsg.code) {
                setMessages(prev => [...prev, userMsg]);
            }

            // Get AI response
            setIsTyping(true);
            try {
                // ✅ Use admin-ajax.php instead of REST API (hides endpoint from Network tab)
                const ajaxUrl = ajaxurl || '/wp-admin/admin-ajax.php';

                // ✅ Use FormData for WordPress admin-ajax.php (standard WordPress AJAX format)
                const formData = new FormData();
                formData.append('action', 'heytrisha_query');
                formData.append('endpoint', 'query');
                formData.append('query', userMessage);

                // ✅ Include security nonce expected by heytrisha_ajax_query_handler()
                // Prefer dedicated chatbotNonce, fall back to REST nonce if needed
                if (chatbotNonce || nonce) {
                    formData.append('nonce', chatbotNonce || nonce);
                }
                
                const aiResponse = await fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData // FormData automatically sets Content-Type with boundary
                });

                const aiData = await aiResponse.json();
                let assistantContent = '';
                let formattedData = null;
                
                if (aiData.success) {
                    // Use the friendly message from API if available
                    assistantContent = aiData.message || 'Here\'s what I found:';
                    
                    // Format the data for display
                    if (aiData.data && Array.isArray(aiData.data) && aiData.data.length > 0) {
                        formattedData = formatDataForDisplay(aiData.data);
                    } else if (aiData.data && typeof aiData.data === 'object') {
                        formattedData = formatDataForDisplay([aiData.data]);
                    }
                } else {
                    assistantContent = aiData.message || 'Sorry, something went wrong. Please try again.';
                }

                // Add assistant message
                const assistantMsgResponse = await fetch(`${restUrl}chats/${chat.id}/messages`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': nonce
                    },
                    body: JSON.stringify({
                        role: 'assistant',
                        content: assistantContent,
                        metadata: {
                            ...aiData,
                            formattedData: formattedData
                        }
                    })
                });
                const assistantMsg = await assistantMsgResponse.json();
                if (assistantMsg && !assistantMsg.code) {
                    // Add formattedData to the message object
                    assistantMsg.formattedData = formattedData;
                    setMessages(prev => [...prev, assistantMsg]);
                }

                // Update chat title if it's still "New Chat"
                if (chat.title === 'New Chat' && userMessage.length > 0) {
                    const title = userMessage.substring(0, 50);
                    await fetch(`${restUrl}chats/${chat.id}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': nonce
                        },
                        body: JSON.stringify({ title })
                    });
                    loadChats();
                }
            } catch (error) {
                console.error('Failed to get AI response:', error);
                const errorMsg = {
                    role: 'assistant',
                    content: 'Sorry, something went wrong. Please try again.',
                    metadata: { error: error.message }
                };
                setMessages(prev => [...prev, errorMsg]);
            } finally {
                setIsTyping(false);
            }
        };

        // Format data for display (returns formatted object, not string)
        const formatDataForDisplay = (data) => {
            if (!data || !Array.isArray(data) || data.length === 0) {
                return null;
            }
            
            return {
                type: "table",
                content: data,
                summary: `Found ${data.length} result${data.length > 1 ? 's' : ''}`
            };
        };
        
        // Render formatted data component
        const renderFormattedData = (formattedData) => {
            if (!formattedData) return null;
            
            // Handle string (JSON) - try to parse
            if (typeof formattedData === "string") {
                try {
                    formattedData = JSON.parse(formattedData);
                } catch (e) {
                    return null;
                }
            }
            
            if (formattedData.type === "table") {
                const displayData = Array.isArray(formattedData.content) 
                    ? formattedData.content.slice(0, 15) 
                    : [];
                
                return React.createElement("div", { 
                    className: "heytrisha-formatted-data",
                    style: { marginTop: "12px" }
                },
                    formattedData.summary && React.createElement("div", {
                        className: "heytrisha-data-summary",
                        style: {
                            marginBottom: "12px",
                            fontWeight: "600",
                            color: "#10a37f",
                            fontSize: "13px",
                            display: "flex",
                            alignItems: "center",
                            gap: "6px"
                        }
                    },
                        React.createElement("span", null, "📊"),
                        React.createElement("span", null, formattedData.summary)
                    ),
                    React.createElement("div", {
                        className: "heytrisha-data-table",
                        style: {
                            border: "1px solid #4d4d4f",
                            borderRadius: "8px",
                            backgroundColor: "#40414f",
                            overflow: "hidden"
                        }
                    },
                        displayData.map((item, idx) => {
                            const entries = Object.entries(item).filter(([key]) => 
                                !key.includes('_meta') && !key.includes('_cache')
                            );
                            
                            return React.createElement("div", {
                                key: idx,
                                style: {
                                    padding: "14px 16px",
                                    borderBottom: idx < displayData.length - 1 ? "1px solid #4d4d4f" : "none",
                                    backgroundColor: idx % 2 === 0 ? "#40414f" : "#343541"
                                }
                            },
                                entries.slice(0, 10).map(([key, value], i) => {
                                    const readableKey = key
                                        .replace(/_/g, " ")
                                        .replace(/\b\w/g, l => l.toUpperCase())
                                        .replace(/Id/g, "ID")
                                        .replace(/Url/g, "URL");
                                    
                                    // Format numeric values
                                    let displayValue = value;
                                    if (typeof value === 'number' && value > 1000) {
                                        displayValue = value.toLocaleString();
                                    } else if (typeof value === 'string' && /^\d+\.\d+$/.test(value)) {
                                        const num = parseFloat(value);
                                        if (num > 1000) {
                                            displayValue = num.toLocaleString('en-US', { 
                                                minimumFractionDigits: 2, 
                                                maximumFractionDigits: 2 
                                            });
                                        }
                                    }
                                    
                                    return React.createElement("div", {
                                        key: i,
                                        style: {
                                            display: "flex",
                                            justifyContent: "space-between",
                                            marginBottom: i < entries.length - 1 ? "8px" : "0",
                                            fontSize: "13px",
                                            gap: "12px"
                                        }
                                    },
                                        React.createElement("span", {
                                            style: {
                                                fontWeight: "600",
                                                color: "#ececf1",
                                                minWidth: "140px",
                                                flexShrink: 0
                                            }
                                        }, readableKey + ":"),
                                        React.createElement("span", {
                                            style: {
                                                color: "#8e8ea0",
                                                textAlign: "right",
                                                wordBreak: "break-word",
                                                flex: 1
                                            }
                                        }, displayValue === null || displayValue === undefined ? "N/A" : String(displayValue))
                                    );
                                })
                            );
                        })
                    )
                );
            }
            
            return null;
        };

        // Handle Enter key
        const handleKeyPress = (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        };

        return React.createElement("div", { className: "heytrisha-chat-container" },
            // Main chat area with sidebar inside
            React.createElement("div", { className: "heytrisha-chat-main" },
                // Sidebar inside main container
                React.createElement("div", { 
                    className: `heytrisha-chat-sidebar ${sidebarOpen ? '' : 'hidden'}` 
                },
                    React.createElement("div", { className: "heytrisha-chat-sidebar-header" },
                        React.createElement("button", {
                            className: "heytrisha-new-chat-btn",
                            onClick: createNewChat
                        },
                            React.createElement("span", null, "➕"),
                            React.createElement("span", null, "New Chat")
                        )
                    ),
                    React.createElement("div", { className: "heytrisha-chat-list" },
                        chats.map(chat => 
                            React.createElement("div", {
                                key: chat.id,
                                className: `heytrisha-chat-item ${currentChat?.id === chat.id ? 'active' : ''}`,
                                onClick: () => loadChat(chat.id)
                            },
                                React.createElement("span", { className: "heytrisha-chat-item-title" }, chat.title),
                                React.createElement("span", null, "→")
                            )
                        )
                    )
                ),
                // Chat content area
                React.createElement("div", { className: "heytrisha-chat-content" },
                React.createElement("div", { className: "heytrisha-chat-header" },
                    React.createElement("button", {
                        className: "heytrisha-sidebar-toggle",
                        onClick: () => setSidebarOpen(!sidebarOpen)
                    }, "☰"),
                    React.createElement("h1", null, currentChat?.title || "New Chat")
                ),
                React.createElement("div", { className: "heytrisha-chat-messages" },
                    messages.length === 0 && !isLoading ? 
                        React.createElement("div", { className: "heytrisha-empty-state" },
                            React.createElement("h2", null, "Hey Trisha"),
                            React.createElement("p", null, "Start a conversation by typing a message below.")
                        ) :
                        messages.map(msg => 
                            React.createElement("div", {
                                key: msg.id,
                                className: `heytrisha-message ${msg.role}`
                            },
                                React.createElement("div", { className: "heytrisha-message-avatar" },
                                    msg.role === 'user' ? 'U' : 'T'
                                ),
                                React.createElement("div", { className: "heytrisha-message-content" },
                                    React.createElement("div", {
                                        style: {
                                            whiteSpace: 'pre-wrap',
                                            fontFamily: 'inherit',
                                            margin: 0,
                                            marginBottom: msg.formattedData ? "8px" : "0",
                                            lineHeight: "1.6"
                                        }
                                    }, msg.content),
                                    msg.formattedData && renderFormattedData(msg.formattedData)
                                )
                            )
                        ),
                    isTyping && 
                        React.createElement("div", { className: "heytrisha-message assistant" },
                            React.createElement("div", { className: "heytrisha-message-avatar" }, "T"),
                            React.createElement("div", { className: "heytrisha-message-content" },
                                React.createElement("div", { className: "heytrisha-typing-indicator" },
                                    React.createElement("div", { className: "heytrisha-typing-dot" }),
                                    React.createElement("div", { className: "heytrisha-typing-dot" }),
                                    React.createElement("div", { className: "heytrisha-typing-dot" })
                                )
                            )
                        ),
                    React.createElement("div", { ref: messagesEndRef })
                ),
                React.createElement("div", { className: "heytrisha-chat-input-container" },
                    React.createElement("div", { className: "heytrisha-chat-input-wrapper" },
                        React.createElement("textarea", {
                            ref: inputRef,
                            className: "heytrisha-chat-input",
                            value: inputText,
                            onChange: (e) => setInputText(e.target.value),
                            onKeyPress: handleKeyPress,
                            placeholder: "Type a message...",
                            rows: 1
                        }),
                        React.createElement("button", {
                            className: "heytrisha-chat-send-btn",
                            onClick: sendMessage,
                            disabled: !inputText.trim() || isTyping
                        }, "→")
                    )
                ),
                React.createElement("div", { className: "heytrisha-chat-footer" },
                    React.createElement("a", {
                        href: "https://heytrisha.com",
                        target: "_blank",
                        rel: "noopener noreferrer",
                        className: "heytrisha-footer-link"
                    }, "HeyTrisha")
                )
                )
            )
        );
    };

    ReactDOM.render(React.createElement(ChatAdmin), root);
});


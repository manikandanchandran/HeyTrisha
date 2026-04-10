// Wait for both DOM and React to be ready
function initChatbot() {
    // Check if React and ReactDOM are available
    if (typeof React === 'undefined' || typeof ReactDOM === 'undefined') {
        console.warn("⚠️ React not yet loaded, waiting...");
        setTimeout(initChatbot, 100);
        return;
    }

    console.log("✅ chatbot.js loaded...");
    console.log("✅ React version:", React.version || 'unknown');
    console.log("✅ ReactDOM available:", typeof ReactDOM !== 'undefined');

    let chatbotRoot = document.getElementById("chatbot-root");
    if (!chatbotRoot) {
        console.error("❌ #chatbot-root div is missing!");
        return;
    }

    console.log("✅ #chatbot-root found! Mounting React...");

    const Chatbot = () => {
        const [messages, setMessages] = React.useState([
            { 
                sender: "bot", 
                text: "Hello! 👋 I'm Trisha, your AI assistant. How can I help you today?", 
                timestamp: new Date() 
            }
        ]);
        const [inputText, setInputText] = React.useState("");
        const [isMinimized, setIsMinimized] = React.useState(true);
        const [isTyping, setIsTyping] = React.useState(false);
        const [pendingConfirmation, setPendingConfirmation] = React.useState(null);
        const [currentChatId, setCurrentChatId] = React.useState(null);
        const messagesEndRef = React.useRef(null);
        const inputRef = React.useRef(null);
        
        // REST API config kept for potential future use
        // Chat operations now use admin-ajax.php for shared hosting compatibility

        // Auto-scroll to bottom when new message arrives
        React.useEffect(() => {
            messagesEndRef.current?.scrollIntoView({ behavior: "smooth" });
        }, [messages, isTyping]);

        // Focus input when chat opens
        React.useEffect(() => {
            if (!isMinimized && inputRef.current) {
                inputRef.current.focus();
            }
        }, [isMinimized]);
        
        // REMOVED: Server auto-start code
        // Plugin now uses external API, no local server needed
        
        // Get AJAX config for chat operations (uses admin-ajax.php for shared hosting compatibility)
        const chatAjaxUrl = (window.heytrishaConfig && window.heytrishaConfig.ajaxurl) || '/wp-admin/admin-ajax.php';
        const chatNonce = (window.heytrishaConfig && window.heytrishaConfig.nonce) || '';
        
        // Create or load chat when chatbot opens
        React.useEffect(() => {
            if (!isMinimized && !currentChatId && chatAjaxUrl && chatNonce) {
                createOrLoadChat();
            }
        }, [isMinimized]);
        
        // Create or load chat (uses admin-ajax.php for shared hosting compatibility)
        const createOrLoadChat = async () => {
            if (!chatAjaxUrl || !chatNonce) {
                setMessages([{
                    sender: "bot",
                    text: "Hello! 👋 I'm Trisha, your AI assistant. How can I help you today?",
                    timestamp: new Date()
                }]);
                return;
            }
            
            try {
                // Try to get the most recent active chat via AJAX
                const formData = new FormData();
                formData.append('action', 'heytrisha_get_chats');
                formData.append('nonce', chatNonce);
                formData.append('archived', 'false');
                
                const chatsResponse = await fetch(chatAjaxUrl, {
                    method: 'POST',
                    body: formData
                });
                
                if (!chatsResponse.ok) {
                    console.warn('Chat API not available, continuing without chat history');
                    setMessages([{
                        sender: "bot",
                        text: "Hello! 👋 I'm Trisha, your AI assistant. How can I help you today?",
                        timestamp: new Date()
                    }]);
                    return;
                }
                
                const result = await chatsResponse.json();
                const chats = result.success ? result.data : null;
                
                if (chats && Array.isArray(chats) && chats.length > 0) {
                    // Load the most recent chat
                    const recentChat = chats[0];
                    setCurrentChatId(recentChat.id);
                    loadChatMessages(recentChat.id);
                } else {
                    // Create new chat via AJAX
                    const createForm = new FormData();
                    createForm.append('action', 'heytrisha_create_chat');
                    createForm.append('nonce', chatNonce);
                    createForm.append('title', 'Chat Widget');
                    
                    const createResponse = await fetch(chatAjaxUrl, {
                        method: 'POST',
                        body: createForm
                    });
                    
                    if (createResponse.ok) {
                        const createResult = await createResponse.json();
                        if (createResult.success && createResult.data) {
                            setCurrentChatId(createResult.data.id);
                            setMessages([{
                                sender: "bot",
                                text: "Hello! 👋 I'm Trisha, your AI assistant. How can I help you today?",
                                timestamp: new Date()
                            }]);
                        }
                    } else {
                        console.warn('Chat creation failed, continuing without chat history');
                        setMessages([{
                            sender: "bot",
                            text: "Hello! 👋 I'm Trisha, your AI assistant. How can I help you today?",
                            timestamp: new Date()
                        }]);
                    }
                }
            } catch (error) {
                console.warn('Failed to create/load chat, continuing without chat history:', error);
                setMessages([{
                    sender: "bot",
                    text: "Hello! 👋 I'm Trisha, your AI assistant. How can I help you today?",
                    timestamp: new Date()
                }]);
            }
        };
        
        // Load chat messages (uses admin-ajax.php)
        const loadChatMessages = async (chatId) => {
            if (!chatAjaxUrl || !chatNonce) return;
            
            try {
                const formData = new FormData();
                formData.append('action', 'heytrisha_get_chat');
                formData.append('nonce', chatNonce);
                formData.append('chat_id', chatId);
                
                const response = await fetch(chatAjaxUrl, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                const chat = result.success ? result.data : null;
                if (chat && chat.messages) {
                    // Convert database messages to chatbot format
                    const formattedMessages = chat.messages.map(msg => {
                        const messageObj = {
                            sender: msg.role === 'user' ? 'user' : 'bot',
                            text: msg.content,
                            timestamp: new Date(msg.created_at)
                        };
                        
                        // Extract formattedData from metadata if it exists
                        // Metadata is stored as JSON string in database, so we need to parse it first
                        let parsedMetadata = null;
                        if (msg.metadata) {
                            try {
                                // If metadata is a string, parse it
                                if (typeof msg.metadata === 'string') {
                                    parsedMetadata = JSON.parse(msg.metadata);
                                } else if (typeof msg.metadata === 'object') {
                                    // Already an object
                                    parsedMetadata = msg.metadata;
                                }
                            } catch (e) {
                                console.warn('Failed to parse metadata:', e);
                                parsedMetadata = null;
                            }
                        }
                        
                        // Now extract formattedData from parsed metadata
                        if (parsedMetadata && parsedMetadata.formattedData) {
                            // formattedData might also be a JSON string, so parse it if needed
                            if (typeof parsedMetadata.formattedData === 'string') {
                                try {
                                    messageObj.formattedData = JSON.parse(parsedMetadata.formattedData);
                                } catch (e) {
                                    console.warn('Failed to parse formattedData:', e);
                                    messageObj.formattedData = parsedMetadata.formattedData;
                                }
                            } else {
                                messageObj.formattedData = parsedMetadata.formattedData;
                            }
                        } else if (parsedMetadata && parsedMetadata.data) {
                            // If metadata has data but no formattedData, try to reconstruct formattedData
                            // This handles cases where data was saved but formattedData wasn't
                            try {
                                const data = Array.isArray(parsedMetadata.data) ? parsedMetadata.data : [parsedMetadata.data];
                                if (data.length > 0) {
                                    messageObj.formattedData = formatResponse(data);
                                }
                            } catch (e) {
                                console.warn('Failed to reconstruct formattedData from data:', e);
                            }
                        }
                        
                        // If content contains JSON at the end, try to extract it (fallback)
                        if (!messageObj.formattedData && msg.content && msg.content.includes('{')) {
                            try {
                                const jsonMatch = msg.content.match(/\{[\s\S]*\}$/);
                                if (jsonMatch) {
                                    const parsed = JSON.parse(jsonMatch[0]);
                                    if (parsed.type && (parsed.type === 'table' || parsed.type === 'details' || parsed.type === 'card')) {
                                        messageObj.formattedData = parsed;
                                        // Remove JSON from text
                                        messageObj.text = msg.content.replace(/\s*\{[\s\S]*\}$/, '').trim();
                                    }
                                }
                            } catch (e) {
                                // Ignore parsing errors
                            }
                        }
                        
                        return messageObj;
                    });
                    setMessages(formattedMessages.length > 0 ? formattedMessages : [{
                        sender: "bot",
                        text: "Hello! 👋 I'm Trisha, your AI assistant. How can I help you today?",
                        timestamp: new Date()
                    }]);
                }
            } catch (error) {
                console.error('Failed to load chat messages:', error);
            }
        };
        
        // Save message to database (uses admin-ajax.php)
        const saveMessageToDatabase = async (role, content, metadata = null) => {
            if (!currentChatId || !chatAjaxUrl || !chatNonce) return;
            
            try {
                const formData = new FormData();
                formData.append('action', 'heytrisha_save_message');
                formData.append('nonce', chatNonce);
                formData.append('chat_id', currentChatId);
                formData.append('role', role);
                formData.append('content', content);
                if (metadata) {
                    formData.append('metadata', typeof metadata === 'string' ? metadata : JSON.stringify(metadata));
                }
                
                await fetch(chatAjaxUrl, {
                    method: 'POST',
                    body: formData
                });
            } catch (error) {
                console.error('Failed to save message to database:', error);
            }
        };
        
        // Update chat title if needed (uses admin-ajax.php)
        const updateChatTitleIfNeeded = async (chatId, firstMessage) => {
            if (!chatAjaxUrl || !chatNonce) return;
            
            try {
                // Get current chat to check title
                const getForm = new FormData();
                getForm.append('action', 'heytrisha_get_chat');
                getForm.append('nonce', chatNonce);
                getForm.append('chat_id', chatId);
                
                const chatResponse = await fetch(chatAjaxUrl, {
                    method: 'POST',
                    body: getForm
                });
                const result = await chatResponse.json();
                const chat = result.success ? result.data : null;
                
                if (chat && (chat.title === 'Chat Widget' || chat.title === 'New Chat')) {
                    const newTitle = firstMessage.substring(0, 50);
                    const updateForm = new FormData();
                    updateForm.append('action', 'heytrisha_update_chat');
                    updateForm.append('nonce', chatNonce);
                    updateForm.append('chat_id', chatId);
                    updateForm.append('title', newTitle);
                    
                    await fetch(chatAjaxUrl, {
                        method: 'POST',
                        body: updateForm
                    });
                }
            } catch (error) {
                console.error('Failed to update chat title:', error);
            }
        };

        // ✅ Enhanced response formatting
        const formatResponse = (data) => {
            if (data === null || data === undefined) {
                return { type: "text", content: "No data available." };
            }

            if (typeof data === "string") {
                return { type: "text", content: data };
            }

            if (Array.isArray(data)) {
                if (data.length === 0) {
                    return { type: "text", content: "No results found." };
                }

                if (data.length > 0 && typeof data[0] === "object") {
                    return {
                        type: "table",
                        content: data,
                        summary: `Found ${data.length} result${data.length > 1 ? 's' : ''}`
                    };
                }

                return {
                    type: "list",
                    content: data,
                    summary: `Found ${data.length} item${data.length > 1 ? 's' : ''}`
                };
            }

            if (typeof data === "object") {
                if (data.title || data.name || data.post_title || data.product_name) {
                    return {
                        type: "card",
                        content: data,
                        title: data.title || data.name || data.post_title || data.product_name || "Item Details"
                    };
                }

                const keys = Object.keys(data);
                if (keys.length > 0) {
                    return {
                        type: "details",
                        content: data,
                        summary: "Details"
                    };
                }

                return { type: "text", content: "Empty response." };
            }

            return { type: "text", content: String(data) };
        };

        // ✅ Format timestamp
        const formatTime = (date) => {
            if (!date) return "";
            const d = new Date(date);
            return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        };

        // ✅ Render formatted message content
        const renderMessageContent = (formattedData) => {
            // Handle case where formattedData might be a JSON string
            if (typeof formattedData === "string") {
                try {
                    formattedData = JSON.parse(formattedData);
                } catch (e) {
                    // If it's not JSON, treat as text content
                    return React.createElement("div", { 
                        style: { 
                            whiteSpace: "pre-wrap", 
                            lineHeight: "1.6",
                            wordBreak: "break-word"
                        } 
                    }, formattedData);
                }
            }
            
            if (!formattedData || formattedData.type === "text") {
                return React.createElement("div", { 
                    style: { 
                        whiteSpace: "pre-wrap", 
                        lineHeight: "1.6",
                        wordBreak: "break-word"
                    } 
                }, formattedData?.content || "");
            }

            if (formattedData.type === "table") {
                const maxRows = 15;
                const displayData = Array.isArray(formattedData.content) ? formattedData.content.slice(0, maxRows) : [];
                
                return React.createElement("div", { style: { marginTop: "12px" } },
                    formattedData.summary && React.createElement("div", { 
                        style: { 
                            marginBottom: "12px", 
                            fontWeight: "600", 
                            color: "#1e40af",
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
                        style: {
                            maxHeight: "400px",
                            overflowY: "auto",
                            border: "1px solid #e5e7eb",
                            borderRadius: "12px",
                            backgroundColor: "#ffffff",
                            boxShadow: "0 1px 3px rgba(0,0,0,0.1)"
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
                                    borderBottom: idx < displayData.length - 1 ? "1px solid #f3f4f6" : "none",
                                    backgroundColor: idx % 2 === 0 ? "#ffffff" : "#f9fafb",
                                    transition: "background-color 0.2s"
                                }
                            },
                                entries.slice(0, 8).map(([key, value], i) => {
                                    const readableKey = key
                                        .replace(/_/g, " ")
                                        .replace(/\b\w/g, l => l.toUpperCase())
                                        .replace(/Id/g, "ID")
                                        .replace(/Url/g, "URL");
                                    
                                    const displayValue = value === null || value === undefined 
                                        ? "N/A" 
                                        : String(value).length > 100 
                                            ? String(value).substring(0, 100) + "..." 
                                            : String(value);
                                    
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
                                                color: "#374151", 
                                                minWidth: "140px",
                                                flexShrink: 0
                                            } 
                                        }, readableKey + ":"),
                                        React.createElement("span", { 
                                            style: { 
                                                color: "#6b7280", 
                                                textAlign: "right", 
                                                wordBreak: "break-word",
                                                flex: 1
                                            } 
                                        }, displayValue)
                                    );
                                })
                            );
                        }),
                        formattedData.content.length > maxRows && React.createElement("div", {
                            style: {
                                padding: "12px",
                                textAlign: "center",
                                color: "#6b7280",
                                fontSize: "12px",
                                fontStyle: "italic",
                                backgroundColor: "#f9fafb",
                                borderTop: "1px solid #e5e7eb"
                            }
                        }, `... and ${formattedData.content.length - maxRows} more result${formattedData.content.length - maxRows > 1 ? 's' : ''}`)
                    )
                );
            }

            if (formattedData.type === "card") {
                const entries = Object.entries(formattedData.content).filter(([key]) => 
                    key !== 'title' && key !== 'name' && key !== 'post_title' && key !== 'product_name'
                );
                
                return React.createElement("div", {
                    style: {
                        marginTop: "12px",
                        padding: "16px",
                        backgroundColor: "#eff6ff",
                        border: "1px solid #bfdbfe",
                        borderRadius: "12px",
                        boxShadow: "0 1px 3px rgba(0,0,0,0.1)"
                    }
                },
                    React.createElement("div", { 
                        style: { 
                            fontWeight: "700", 
                            fontSize: "16px", 
                            marginBottom: "12px", 
                            color: "#1e40af" 
                        } 
                    }, `📋 ${formattedData.title}`),
                    entries.slice(0, 10).map(([key, value], i) => {
                        const readableKey = key
                            .replace(/_/g, " ")
                            .replace(/\b\w/g, l => l.toUpperCase())
                            .replace(/Id/g, "ID");
                        
                        return React.createElement("div", {
                            key: i,
                            style: {
                                display: "flex",
                                marginBottom: "10px",
                                fontSize: "13px",
                                gap: "12px"
                            }
                        },
                            React.createElement("span", { 
                                style: { 
                                    fontWeight: "600", 
                                    color: "#374151", 
                                    minWidth: "120px",
                                    flexShrink: 0
                                } 
                            }, readableKey + ":"),
                            React.createElement("span", { 
                                style: { 
                                    color: "#6b7280", 
                                    wordBreak: "break-word",
                                    flex: 1
                                } 
                            }, value === null || value === undefined ? "N/A" : String(value))
                        );
                    })
                );
            }

            if (formattedData.type === "details") {
                const entries = Object.entries(formattedData.content);
                return React.createElement("div", {
                    style: {
                        marginTop: "12px",
                        padding: "16px",
                        backgroundColor: "#f9fafb",
                        border: "1px solid #e5e7eb",
                        borderRadius: "12px"
                    }
                },
                    entries.map(([key, value], i) => {
                        const readableKey = key
                            .replace(/_/g, " ")
                            .replace(/\b\w/g, l => l.toUpperCase())
                            .replace(/Id/g, "ID");
                        
                        return React.createElement("div", {
                            key: i,
                            style: {
                                display: "flex",
                                marginBottom: "10px",
                                fontSize: "13px",
                                gap: "12px"
                            }
                        },
                            React.createElement("span", { 
                                style: { 
                                    fontWeight: "600", 
                                    color: "#374151", 
                                    minWidth: "140px",
                                    flexShrink: 0
                                } 
                            }, readableKey + ":"),
                            React.createElement("span", { 
                                style: { 
                                    color: "#6b7280", 
                                    wordBreak: "break-word",
                                    flex: 1
                                } 
                            }, value === null || value === undefined ? "N/A" : String(value))
                        );
                    })
                );
            }

            if (formattedData.type === "list") {
                return React.createElement("div", { style: { marginTop: "12px" } },
                    React.createElement("div", { 
                        style: { 
                            marginBottom: "10px", 
                            fontWeight: "600", 
                            color: "#1e40af",
                            fontSize: "13px"
                        } 
                    }, `📋 ${formattedData.summary}`),
                    React.createElement("ul", { 
                        style: { 
                            margin: 0, 
                            paddingLeft: "24px",
                            listStyleType: "disc"
                        } 
                    },
                        formattedData.content.map((item, idx) =>
                            React.createElement("li", { 
                                key: idx, 
                                style: { 
                                    marginBottom: "6px", 
                                    color: "#374151",
                                    lineHeight: "1.6"
                                } 
                            }, String(item))
                        )
                    )
                );
            }

            return React.createElement("div", null, String(formattedData.content));
        };

        const handleSendMessage = async (confirmed = false, confirmationData = null) => {
            const queryText = confirmed ? inputText : (inputText.trim() || "");
            if (!queryText && !confirmed) return;

            if (!confirmed) {
                console.log("✅ Sending message:", queryText);
                const userMessage = { 
                    sender: "user", 
                    text: queryText,
                    timestamp: new Date()
                };
                setMessages(prevMessages => [...prevMessages, userMessage]);
                setInputText("");
                
                // Save user message to database
                if (currentChatId) {
                    saveMessageToDatabase('user', queryText);
                    // Update chat title if it's still "Chat Widget"
                    updateChatTitleIfNeeded(currentChatId, queryText);
                } else if (chatAjaxUrl && chatNonce) {
                    // Create chat first if it doesn't exist (via admin-ajax.php)
                    try {
                        const createForm = new FormData();
                        createForm.append('action', 'heytrisha_create_chat');
                        createForm.append('nonce', chatNonce);
                        createForm.append('title', queryText.substring(0, 50));
                        
                        const createResponse = await fetch(chatAjaxUrl, {
                            method: 'POST',
                            body: createForm
                        });
                        const createResult = await createResponse.json();
                        if (createResult.success && createResult.data) {
                            setCurrentChatId(createResult.data.id);
                            saveMessageToDatabase('user', queryText);
                        }
                    } catch (error) {
                        console.error('Failed to create chat:', error);
                    }
                }
            }
            
            setIsTyping(true);

            try {
                const requestBody = confirmed && confirmationData
                    ? { 
                        query: confirmationData.original_query || "",
                        confirmed: true,
                        confirmation_data: confirmationData
                      }
                    : { query: queryText };

                // ✅ Create AbortController for timeout (reduced to 20 seconds for faster feedback)
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 20000); // 20 second timeout

                // ✅ Use admin-ajax.php instead of REST API (hides endpoint from Network tab)
                const ajaxUrl = (window.heytrishaConfig && window.heytrishaConfig.ajaxurl) || '/wp-admin/admin-ajax.php';
                const nonce = (window.heytrishaConfig && window.heytrishaConfig.nonce) || '';
                
                console.log('🌐 Using admin-ajax.php (secure endpoint)');
                console.log('🔑 Nonce available:', nonce ? 'Yes (' + nonce.substring(0, 10) + '...)' : 'No');
                
                if (!nonce) {
                    console.error('❌ Nonce not found in window.heytrishaConfig. Config:', window.heytrishaConfig);
                    setIsTyping(false);
                    setMessages(prevMessages => [...prevMessages, { 
                        sender: "bot", 
                        text: "Security error: Please refresh the page and try again.",
                        timestamp: new Date()
                    }]);
                    return;
                }
                
                let response;
                try {
                    // ✅ Send request to admin-ajax.php with form-encoded data (WordPress standard)
                    const formData = new FormData();
                    formData.append('action', 'heytrisha_query');
                    formData.append('nonce', nonce); // ✅ Add nonce for security
                    formData.append('endpoint', 'query');
                    
                    // Debug: Log FormData contents
                    console.log('📤 FormData contents:');
                    for (let pair of formData.entries()) {
                        console.log('  ' + pair[0] + ': ' + (pair[0] === 'nonce' ? pair[1].substring(0, 10) + '...' : pair[1]));
                    }
                    
                    // Add query parameters as JSON string
                    if (requestBody.query) {
                        formData.append('query', requestBody.query);
                    }
                    if (requestBody.confirmed !== undefined) {
                        formData.append('confirmed', requestBody.confirmed);
                    }
                    if (requestBody.confirmation_data) {
                        formData.append('confirmation_data', JSON.stringify(requestBody.confirmation_data));
                    }
                    
                    response = await fetch(ajaxUrl, {
                        method: "POST",
                        body: formData,
                        signal: controller.signal
                    });
                    clearTimeout(timeoutId);
                } catch (fetchError) {
                    clearTimeout(timeoutId);
                    if (fetchError.name === 'AbortError') {
                        throw new Error('Request timeout: Server took too long to respond. Please try again.');
                    }
                    // Check if it's a connection error (server not ready)
                    if (fetchError.message && (fetchError.message.includes('Failed to fetch') || fetchError.message.includes('NetworkError'))) {
                        // REMOVED: Server start retry logic
                        // Plugin now uses external API, no local server needed
                        console.log('⚠️ Connection failed. Please check your API configuration in settings.');
                        throw new Error('Could not connect to server. Please check your API configuration in settings.');
                    } else {
                        throw fetchError;
                    }
                }

                if (!response.ok) {
                    throw new Error(`Server error: ${response.status} ${response.statusText}`);
                }

                let data = await response.json();
                console.log("✅ API Response:", data);

                setIsTyping(false);

                if (data.success) {
                    if (data.requires_confirmation && data.confirmation_data) {
                        setPendingConfirmation(data.confirmation_data);
                        const confirmationMessage = {
                            sender: "bot",
                            text: data.confirmation_message || "Please confirm this action.",
                            requiresConfirmation: true,
                            confirmationData: data.confirmation_data,
                            timestamp: new Date()
                        };
                        setMessages(prevMessages => [...prevMessages, confirmationMessage]);
                        
                        // Save confirmation message to database
                        if (currentChatId) {
                            saveMessageToDatabase('assistant', confirmationMessage.text, data.confirmation_data);
                        }
                    } else {
                        setPendingConfirmation(null);
                        
                        let botMessage;
                        // ✅ Check if data.data is empty array, null, or undefined
                        const isEmptyData = data.data === null || 
                                          data.data === undefined || 
                                          (Array.isArray(data.data) && data.data.length === 0);
                        
                        // ✅ Always use data.message if available, even when data.data is empty/null/undefined
                        // This ensures we show the actual response from the backend, not a generic fallback
                        if (data.message && isEmptyData) {
                            // Backend returned a message but no data (e.g., "No matching records found")
                            botMessage = {
                                sender: "bot",
                                text: data.message,
                                timestamp: new Date()
                            };
                        } else if (isEmptyData) {
                            // No message and no data - use fallback
                            botMessage = {
                                sender: "bot",
                                text: data.message || "I received your message, but there's no data to display. Try asking me to show posts, products, or edit something specific.",
                                timestamp: new Date()
                            };
                        } else {
                            // Has data - format it
                            const formattedResponse = formatResponse(data.data);
                            botMessage = {
                                sender: "bot",
                                text: data.message || formattedResponse.summary || "Here's what I found:",
                                formattedData: formattedResponse,
                                timestamp: new Date()
                            };
                        }
                        setMessages(prevMessages => [...prevMessages, botMessage]);
                        
                        // Save bot message to database
                        if (currentChatId) {
                            // Save only the text message, formattedData goes in metadata
                            saveMessageToDatabase('assistant', botMessage.text, {
                                ...data,
                                formattedData: botMessage.formattedData
                            });
                        }
                    }
                } else {
                    setPendingConfirmation(null);
                    const errorMessage = {
                        sender: "bot",
                        text: data.message || "Sorry, I couldn't process that request. Please try again or rephrase your query.",
                        timestamp: new Date()
                    };
                    setMessages(prevMessages => [...prevMessages, errorMessage]);
                    
                    // Save error message to database
                    if (currentChatId) {
                        saveMessageToDatabase('assistant', errorMessage.text, { error: true });
                    }
                }
            } catch (error) {
                console.error("❌ API Error:", error);
                setIsTyping(false);
                setPendingConfirmation(null);
                
                // Better error messages
                let errorMessage = "Sorry, something went wrong! ";
                if (error.message.includes('timeout') || error.message.includes('Timeout')) {
                    errorMessage += "The server took too long to respond. Please try again in a moment.";
                } else if (error.message.includes('Failed to fetch') || error.message.includes('NetworkError')) {
                    errorMessage += "Could not connect to the API server. Please check if the server is running.";
                } else {
                    errorMessage += error.message || "Please check if the API server is running.";
                }
                
                setMessages(prevMessages => [...prevMessages, { 
                    sender: "bot", 
                    text: errorMessage,
                    timestamp: new Date()
                }]);
            }
        };

        const handleConfirm = () => {
            if (pendingConfirmation) {
                setMessages(prevMessages => [...prevMessages, { 
                    sender: "user", 
                    text: "Yes, proceed with the edit.",
                    timestamp: new Date()
                }]);
                setIsTyping(true);
                handleSendMessage(true, pendingConfirmation);
                setPendingConfirmation(null);
            }
        };

        const handleCancel = () => {
            setPendingConfirmation(null);
            setMessages(prevMessages => [...prevMessages, { 
                sender: "user", 
                text: "Cancel",
                timestamp: new Date()
            }]);
            setMessages(prevMessages => [...prevMessages, { 
                sender: "bot", 
                text: "Edit operation cancelled.",
                timestamp: new Date()
            }]);
        };

        // Get plugin URL from config, ensure it ends with /
        let pluginUrl = (window.heytrishaConfig && window.heytrishaConfig.pluginUrl) || "";
        if (pluginUrl && !pluginUrl.endsWith('/')) {
            pluginUrl += '/';
        }
        
        // Ensure absolute URL (if relative, make it absolute)
        if (pluginUrl && !pluginUrl.startsWith('http') && !pluginUrl.startsWith('//')) {
            // If it's a relative path, make it absolute from site root
            const siteUrl = window.location.origin;
            if (pluginUrl.startsWith('/')) {
                pluginUrl = siteUrl + pluginUrl;
            } else {
                pluginUrl = siteUrl + '/' + pluginUrl;
            }
        }
        
        const botIconUrl = pluginUrl + "assets/img/bot.jpeg";
        const headerLogoUrl = pluginUrl + "assets/img/heytrisha.jpeg";
        
        // Debug: Log URLs to console
        console.log('HeyTrisha: pluginUrl =', pluginUrl);
        console.log('HeyTrisha: botIconUrl =', botIconUrl);
        console.log('HeyTrisha: headerLogoUrl =', headerLogoUrl);
        
        if (!pluginUrl) {
            console.warn('HeyTrisha: pluginUrl is not set. Images may not load correctly.');
        }

        return React.createElement("div", {
            className: "heytrisha-chatbot-container",
            style: {
                width: isMinimized ? "64px" : "360px",
                height: isMinimized ? "64px" : "550px",
                position: "fixed",
                bottom: "20px",
                right: "20px",
                backgroundColor: "white",
                borderRadius: "20px",
                zIndex: "999999",
                overflow: "hidden",
                boxShadow: "0 20px 60px rgba(0,0,0,0.3), 0 0 0 1px rgba(0,0,0,0.05)",
                display: "flex",
                flexDirection: "column",
                fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', 'Fira Sans', 'Droid Sans', 'Helvetica Neue', sans-serif",
                transition: "all 0.3s cubic-bezier(0.4, 0, 0.2, 1)"
            }
        }, 
            // Minimized state - floating button
            isMinimized && React.createElement("div", {
                style: {
                    width: "100%",
                    height: "100%",
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "center",
                    background: "linear-gradient(135deg, #667eea 0%, #764ba2 100%)",
                    cursor: "pointer",
                    borderRadius: "20px",
                    transition: "transform 0.2s",
                    boxShadow: "0 10px 30px rgba(102, 126, 234, 0.4)"
                },
                onClick: () => setIsMinimized(false),
                onMouseEnter: (e) => e.currentTarget.style.transform = "scale(1.05)",
                onMouseLeave: (e) => e.currentTarget.style.transform = "scale(1)"
            },
                React.createElement("div", { 
                    style: { 
                        fontSize: "32px",
                        filter: "drop-shadow(0 2px 4px rgba(0,0,0,0.2))"
                    } 
                }, "💬")
            ),

            // Full chat interface
            !isMinimized && React.createElement(React.Fragment, null,
                // Header
            React.createElement("div", {
                style: {
                        background: "linear-gradient(135deg, #667eea 0%, #764ba2 100%)",
                    color: "white",
                        padding: "16px 20px",
                    display: "flex",
                    justifyContent: "space-between",
                    alignItems: "center",
                        boxShadow: "0 2px 10px rgba(0,0,0,0.1)"
                    }
                },
                    React.createElement("div", { 
                        style: { 
                            display: "flex", 
                            alignItems: "center", 
                            gap: "12px" 
                        } 
                    },
                        React.createElement("img", {
                            src: headerLogoUrl,
                            alt: "Hey Trisha Logo",
                            style: {
                                width: "140px",
                                height: "48px",
                                borderRadius: "8px",
                                objectFit: "cover",
                                boxShadow: "0 2px 8px rgba(0,0,0,0.15)"
                            },
                            onError: (e) => {
                                console.error('Failed to load header logo:', headerLogoUrl);
                                e.target.style.display = "none";
                            },
                            onLoad: () => {
                                console.log('Header logo loaded successfully:', headerLogoUrl);
                            }
                        }),
                        React.createElement("div", null,
                            React.createElement("div", { 
                                style: { 
                                    fontWeight: "700", 
                                    fontSize: "16px",
                                    letterSpacing: "0.3px"
                                } 
                            }, "Hey Trisha"),
                            React.createElement("div", { 
                                style: { 
                                    fontSize: "12px", 
                                    opacity: 0.95,
                                    marginTop: "2px"
                                } 
                            }, "AI Assistant • Online")
                        )
                    ),
                    React.createElement("button", {
                        onClick: () => setIsMinimized(true),
                        style: {
                            background: "rgba(255,255,255,0.2)",
                            border: "none",
                            color: "white",
                            fontSize: "20px",
                            cursor: "pointer",
                            padding: "8px 12px",
                            borderRadius: "8px",
                            transition: "background 0.2s"
                        },
                        onMouseEnter: (e) => e.currentTarget.style.background = "rgba(255,255,255,0.3)",
                        onMouseLeave: (e) => e.currentTarget.style.background = "rgba(255,255,255,0.2)"
                    }, "−")
                ),

                // Messages area
                React.createElement("div", {
                    className: "heytrisha-chatbot-messages",
                    style: {
                        flex: 1,
                        overflowY: "auto",
                        padding: "20px",
                        backgroundColor: "#f8f9fa",
                        backgroundImage: "radial-gradient(circle at 20% 50%, rgba(102, 126, 234, 0.08) 0%, transparent 50%), radial-gradient(circle at 80% 80%, rgba(118, 75, 162, 0.08) 0%, transparent 50%)"
                    }
                },
                messages.map((msg, index) =>
                    React.createElement("div", {
                        key: index,
                        style: {
                            display: "flex",
                            justifyContent: msg.sender === "bot" ? "flex-start" : "flex-end",
                                marginBottom: "20px",
                                animation: "fadeInUp 0.4s ease-out"
                        }
                    },
                            msg.sender === "bot" && React.createElement("img", {
                                src: botIconUrl,
                                alt: "Bot",
                                style: {
                                    width: "36px",
                                    height: "36px",
                                    borderRadius: "50%",
                                    marginRight: "10px",
                                    flexShrink: 0,
                                    objectFit: "cover",
                                    boxShadow: "0 2px 8px rgba(102, 126, 234, 0.3)",
                                    display: "block"
                                },
                                onError: (e) => {
                                    console.error('Failed to load bot icon:', botIconUrl);
                                    e.target.style.display = "none";
                                },
                                onLoad: () => {
                                    console.log('Bot icon loaded successfully:', botIconUrl);
                                }
                            }),
                        React.createElement("div", {
                            style: {
                                    maxWidth: "78%",
                                    padding: "14px 18px",
                                    borderRadius: msg.sender === "bot" 
                                        ? "20px 20px 20px 6px" 
                                        : "20px 20px 6px 20px",
                                    background: msg.sender === "bot" 
                                        ? "white" 
                                        : "linear-gradient(135deg, #667eea 0%, #764ba2 100%)",
                                    color: msg.sender === "bot" ? "#1f2937" : "white",
                                    boxShadow: msg.sender === "bot" 
                                        ? "0 2px 8px rgba(0,0,0,0.08)" 
                                        : "0 2px 8px rgba(102, 126, 234, 0.3)",
                                    fontSize: "14px",
                                    lineHeight: "1.6",
                                    wordBreak: "break-word"
                                }
                            },
                                React.createElement("div", { 
                                    style: { 
                                        marginBottom: msg.formattedData ? "8px" : "0" 
                                    } 
                                }, msg.text),
                                msg.formattedData && renderMessageContent(msg.formattedData),
                                React.createElement("div", {
                                    style: {
                                        marginTop: "6px",
                                        fontSize: "11px",
                                        opacity: 0.7,
                                        textAlign: "right"
                                    }
                                }, formatTime(msg.timestamp)),
                                msg.requiresConfirmation && React.createElement("div", {
                                    style: {
                                        marginTop: "14px",
                                        padding: "14px",
                                        backgroundColor: msg.sender === "bot" ? "#fff3cd" : "rgba(255,255,255,0.2)",
                                        border: "1px solid #ffc107",
                                        borderRadius: "12px"
                                    }
                                },
                                    React.createElement("div", { 
                                        style: { 
                                            fontWeight: "700", 
                                            marginBottom: "12px", 
                                            fontSize: "13px",
                                            color: msg.sender === "bot" ? "#856404" : "white"
                                        } 
                                    }, "⚠️ Confirmation Required"),
                                    React.createElement("div", { 
                                        style: { 
                                            display: "flex", 
                                            gap: "10px", 
                                            marginTop: "12px" 
                                        } 
                                    },
                                        React.createElement("button", {
                                            onClick: handleConfirm,
                                            style: {
                                                padding: "10px 20px",
                                                backgroundColor: "#28a745",
                                                color: "white",
                                                border: "none",
                                                borderRadius: "8px",
                                                cursor: "pointer",
                                                fontWeight: "600",
                                                fontSize: "13px",
                                                transition: "all 0.2s",
                                                boxShadow: "0 2px 4px rgba(40, 167, 69, 0.3)"
                                            },
                                            onMouseEnter: (e) => {
                                                e.currentTarget.style.backgroundColor = "#218838";
                                                e.currentTarget.style.transform = "translateY(-1px)";
                                            },
                                            onMouseLeave: (e) => {
                                                e.currentTarget.style.backgroundColor = "#28a745";
                                                e.currentTarget.style.transform = "translateY(0)";
                                            }
                                        }, "✓ Confirm"),
                                        React.createElement("button", {
                                            onClick: handleCancel,
                                            style: {
                                                padding: "10px 20px",
                                                backgroundColor: "#dc3545",
                                                color: "white",
                                                border: "none",
                                borderRadius: "8px",
                                                cursor: "pointer",
                                                fontWeight: "600",
                                                fontSize: "13px",
                                                transition: "all 0.2s",
                                                boxShadow: "0 2px 4px rgba(220, 53, 69, 0.3)"
                                            },
                                            onMouseEnter: (e) => {
                                                e.currentTarget.style.backgroundColor = "#c82333";
                                                e.currentTarget.style.transform = "translateY(-1px)";
                                            },
                                            onMouseLeave: (e) => {
                                                e.currentTarget.style.backgroundColor = "#dc3545";
                                                e.currentTarget.style.transform = "translateY(0)";
                                            }
                                        }, "✗ Cancel")
                                    )
                                )
                            ),
                            msg.sender === "user" && React.createElement("div", {
                                style: {
                                    width: "36px",
                                    height: "36px",
                                    borderRadius: "50%",
                                    background: "linear-gradient(135deg, #667eea 0%, #764ba2 100%)",
                                    display: "flex",
                                    alignItems: "center",
                                    justifyContent: "center",
                                    marginLeft: "10px",
                                    flexShrink: 0,
                                    fontSize: "20px",
                                    boxShadow: "0 2px 8px rgba(102, 126, 234, 0.3)"
                                }
                            }, "👤")
                    )
                ),
                isTyping && React.createElement("div", {
                    style: {
                            display: "flex",
                            justifyContent: "flex-start",
                            marginBottom: "20px",
                            animation: "fadeInUp 0.3s ease-out"
                        }
                    },
                        React.createElement("img", {
                            src: botIconUrl,
                            alt: "Bot",
                            style: {
                                width: "36px",
                                height: "36px",
                                borderRadius: "50%",
                                marginRight: "10px",
                                flexShrink: 0,
                                objectFit: "cover",
                                boxShadow: "0 2px 8px rgba(102, 126, 234, 0.3)",
                                display: "block"
                            },
                            onError: (e) => {
                                console.error('Failed to load bot icon:', botIconUrl);
                                e.target.style.display = "none";
                            },
                            onLoad: () => {
                                console.log('Bot icon loaded successfully:', botIconUrl);
                            }
                        }),
                        React.createElement("div", {
                            style: {
                                padding: "14px 18px",
                                borderRadius: "20px 20px 20px 6px",
                                backgroundColor: "white",
                                boxShadow: "0 2px 8px rgba(0,0,0,0.08)",
                                display: "flex",
                                gap: "6px",
                                alignItems: "center"
                            }
                        },
                            React.createElement("div", { 
                                style: { 
                                    width: "10px", 
                                    height: "10px", 
                                    borderRadius: "50%", 
                                    backgroundColor: "#9ca3af", 
                                    animation: "bounce 1.4s infinite" 
                                } 
                            }),
                            React.createElement("div", { 
                                style: { 
                                    width: "10px", 
                                    height: "10px", 
                                    borderRadius: "50%", 
                                    backgroundColor: "#9ca3af", 
                                    animation: "bounce 1.4s infinite 0.2s" 
                                } 
                            }),
                            React.createElement("div", { 
                                style: { 
                                    width: "10px", 
                                    height: "10px", 
                                    borderRadius: "50%", 
                                    backgroundColor: "#9ca3af", 
                                    animation: "bounce 1.4s infinite 0.4s" 
                                } 
                            })
                        )
                    ),
                    React.createElement("div", { ref: messagesEndRef })
                ),

                // Input area
                React.createElement("div", {
                    style: {
                        padding: "16px",
                        backgroundColor: "white",
                        borderTop: "1px solid #e5e7eb",
                        boxShadow: "0 -2px 10px rgba(0,0,0,0.05)"
                    }
                },
                    React.createElement("div", { style: { display: "flex", gap: "10px", alignItems: "center" } },
                React.createElement("input", {
                            ref: inputRef,
                    type: "text",
                    value: inputText,
                    onChange: (e) => setInputText(e.target.value),
                            onKeyPress: (e) => {
                                if (e.key === "Enter" && !pendingConfirmation && !e.shiftKey) {
                                    e.preventDefault();
                                    handleSendMessage();
                                }
                            },
                            placeholder: pendingConfirmation ? "Please confirm or cancel..." : "Type a message...",
                            disabled: !!pendingConfirmation,
                            style: {
                                flex: 1,
                                padding: "14px 18px",
                                borderRadius: "24px",
                                border: "2px solid #e5e7eb",
                                fontSize: "14px",
                                outline: "none",
                                opacity: pendingConfirmation ? 0.6 : 1,
                                transition: "all 0.2s",
                                backgroundColor: "#f9fafb"
                            },
                            onFocus: (e) => {
                                e.target.style.borderColor = "#667eea";
                                e.target.style.backgroundColor = "white";
                            },
                            onBlur: (e) => {
                                e.target.style.borderColor = "#e5e7eb";
                                e.target.style.backgroundColor = "#f9fafb";
                            }
                }),
                React.createElement("button", { 
                            onClick: () => !pendingConfirmation && handleSendMessage(),
                            disabled: !!pendingConfirmation || !inputText.trim(),
                            style: {
                                width: "48px",
                                height: "48px",
                                borderRadius: "50%",
                                background: pendingConfirmation || !inputText.trim() 
                                    ? "#d1d5db" 
                                    : "linear-gradient(135deg, #667eea 0%, #764ba2 100%)",
                                color: "white",
                                border: "none",
                                cursor: pendingConfirmation || !inputText.trim() ? "not-allowed" : "pointer",
                                display: "flex",
                                alignItems: "center",
                                justifyContent: "center",
                                fontSize: "22px",
                                transition: "all 0.2s",
                                boxShadow: pendingConfirmation || !inputText.trim() 
                                    ? "none" 
                                    : "0 4px 12px rgba(102, 126, 234, 0.4)"
                            },
                            onMouseEnter: (e) => {
                                if (!pendingConfirmation && inputText.trim()) {
                                    e.currentTarget.style.transform = "scale(1.1)";
                                }
                            },
                            onMouseLeave: (e) => {
                                e.currentTarget.style.transform = "scale(1)";
                            }
                        }, "➤")
                    )
                )
            )
        );
    };

    // CSS is now loaded from external file

    if (typeof React === 'undefined') {
        console.error("❌ React is not loaded. Please check if React script is enqueued correctly.");
        return;
    }
    
    if (typeof ReactDOM === 'undefined') {
        console.error("❌ ReactDOM is not loaded. Please check if ReactDOM script is enqueued correctly.");
        return;
    }
    
    console.log("✅ React version:", React.version || 'unknown');
    console.log("✅ ReactDOM available:", typeof ReactDOM !== 'undefined');
    console.log("✅ ReactDOM.createRoot available:", typeof ReactDOM.createRoot === 'function');
    console.log("✅ ReactDOM.render available:", typeof ReactDOM.render === 'function');

    // ✅ ReactDOM compatibility: use createRoot (React 18+) or fallback to render (React 17)
    try {
        if (typeof ReactDOM.createRoot === 'function') {
            const root = ReactDOM.createRoot(chatbotRoot);
            root.render(React.createElement(Chatbot));
            console.log("✅ React chatbot successfully rendered with createRoot!");
        } else if (typeof ReactDOM.render === 'function') {
            ReactDOM.render(React.createElement(Chatbot), chatbotRoot);
            console.log("✅ React chatbot successfully rendered with render (React 17 fallback)!");
        } else {
            console.error("❌ Neither ReactDOM.createRoot nor ReactDOM.render is available!");
        }
    } catch (renderError) {
        console.error("❌ Failed to render chatbot:", renderError);
        try {
            if (typeof ReactDOM.render === 'function') {
                ReactDOM.render(React.createElement(Chatbot), chatbotRoot);
                console.log("✅ React chatbot rendered with fallback method!");
            } else {
                console.error("❌ ReactDOM.render is not available for fallback!");
            }
        } catch (fallbackError) {
            console.error("❌ All render methods failed:", fallbackError);
        }
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener("DOMContentLoaded", initChatbot);
} else {
    // DOM is already ready, but wait a bit for React to load
    setTimeout(initChatbot, 100);
}

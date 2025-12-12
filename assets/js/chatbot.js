document.addEventListener("DOMContentLoaded", function () {
    console.log("✅ chatbot.js loaded...");

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
        const [isMinimized, setIsMinimized] = React.useState(false);
        const [isTyping, setIsTyping] = React.useState(false);
        const [pendingConfirmation, setPendingConfirmation] = React.useState(null);
        const messagesEndRef = React.useRef(null);
        const inputRef = React.useRef(null);

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
                const displayData = formattedData.content.slice(0, maxRows);
                
                return React.createElement("div", { style: { marginTop: "12px" } },
                    React.createElement("div", { 
                        style: { 
                            marginBottom: "12px", 
                            fontWeight: "600", 
                            color: "#1e40af",
                            fontSize: "13px"
                        } 
                    }, `📊 ${formattedData.summary}`),
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
                setMessages(prevMessages => [...prevMessages, { 
                    sender: "user", 
                    text: queryText,
                    timestamp: new Date()
                }]);
            setInputText("");
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

                let response;
                try {
                    response = await fetch("http://localhost:8000/api/query", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                        body: JSON.stringify(requestBody),
                        signal: controller.signal
                    });
                } catch (fetchError) {
                    clearTimeout(timeoutId);
                    if (fetchError.name === 'AbortError') {
                        throw new Error('Request timeout: Server took too long to respond. Please try again.');
                    }
                    // Check if it's a connection error (server not ready)
                    if (fetchError.message && (fetchError.message.includes('Failed to fetch') || fetchError.message.includes('NetworkError'))) {
                        throw new Error('Could not connect to server. The server may still be starting. Please wait a moment and try again.');
                    }
                    throw fetchError;
                }
                clearTimeout(timeoutId);

                if (!response.ok) {
                    throw new Error(`Server error: ${response.status} ${response.statusText}`);
                }

                let data = await response.json();
                console.log("✅ API Response:", data);

                setIsTyping(false);

                if (data.success) {
                    if (data.requires_confirmation && data.confirmation_data) {
                        setPendingConfirmation(data.confirmation_data);
                        setMessages(prevMessages => [...prevMessages, { 
                            sender: "bot", 
                            text: data.confirmation_message || "Please confirm this action.",
                            requiresConfirmation: true,
                            confirmationData: data.confirmation_data,
                            timestamp: new Date()
                        }]);
                    } else {
                        setPendingConfirmation(null);
                        
                        if (data.data === null || data.data === undefined) {
                            setMessages(prevMessages => [...prevMessages, { 
                                sender: "bot", 
                                text: data.message || "I received your message, but there's no data to display. Try asking me to show posts, products, or edit something specific.",
                                timestamp: new Date()
                            }]);
                        } else {
                            const formattedResponse = formatResponse(data.data);
                            setMessages(prevMessages => [...prevMessages, { 
                                sender: "bot", 
                                text: data.message || formattedResponse.summary || "Here's what I found:",
                                formattedData: formattedResponse,
                                timestamp: new Date()
                            }]);
                        }
                    }
                } else {
                    setPendingConfirmation(null);
                    setMessages(prevMessages => [...prevMessages, { 
                        sender: "bot", 
                        text: data.message || "Sorry, I couldn't process that request. Please try again or rephrase your query.",
                        timestamp: new Date()
                    }]);
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

        const pluginUrl = window.heytrishaPluginUrl || "";
        const botIconUrl = pluginUrl + "assets/js/chatbot-react-app/src/img/bot.jpeg";
        const headerLogoUrl = pluginUrl + "assets/js/chatbot-react-app/src/img/heytrisha.jpeg";

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
                                e.target.style.display = "none";
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
                                    boxShadow: "0 2px 8px rgba(102, 126, 234, 0.3)"
                                },
                                onError: (e) => {
                                    e.target.style.display = "none";
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
                                boxShadow: "0 2px 8px rgba(102, 126, 234, 0.3)"
                            },
                            onError: (e) => {
                                e.target.style.display = "none";
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

    ReactDOM.createRoot(chatbotRoot).render(React.createElement(Chatbot));
    console.log("✅ React chatbot successfully rendered!");
});

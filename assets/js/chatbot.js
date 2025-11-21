// // document.addEventListener("DOMContentLoaded", function () {
// //     console.log("✅ chatbot.js loaded...");

// //     let chatbotRoot = document.getElementById("chatbot-root");
// //     if (!chatbotRoot) {
// //         console.error("❌ #chatbot-root div is missing!");
// //         return;
// //     }

// //     console.log("✅ #chatbot-root found! Mounting React...");

// //     const Chatbot = () => {
// //         const [messages, setMessages] = React.useState([
// //             { sender: "bot", text: "Hello! How can I help you today?" }
// //         ]);
// //         const [inputText, setInputText] = React.useState("");

// //         const handleSendMessage = async () => {
// //             if (!inputText.trim()) return;

// //             console.log("✅ Sending message:", inputText);

// //             // Add user message to chat
// //             setMessages(prevMessages => [...prevMessages, { sender: "user", text: inputText }]);
// //             setInputText(""); // Clear input field

// //             try {
// //                 // Send message to Laravel API
// //                 let response = await fetch("http://localhost:8000/api/query", {
// //                     method: "POST",
// //                     headers: { "Content-Type": "application/json" },
// //                     body: JSON.stringify({ query: inputText })
// //                 });

// //                 let data = await response.json();
// //                 console.log("✅ API Response:", data);

// //                 if (data.success && data.data) {
// //                     // Extract product names from response
// //                     let productNames = data.data.map(item => item.post_title).join(", ");

// //                     // Add bot response with product names
// //                     setMessages(prevMessages => [...prevMessages, { sender: "bot", text: `Here are the last 10 products: ${productNames}` }]);
// //                 } else {
// //                     setMessages(prevMessages => [...prevMessages, { sender: "bot", text: "Sorry, no products found." }]);
// //                 }
// //             } catch (error) {
// //                 console.error("❌ API Error:", error);
// //                 setMessages(prevMessages => [...prevMessages, { sender: "bot", text: "Sorry, something went wrong!" }]);
// //             }
// //         };

// //         return React.createElement("div", {
// //             style: {
// //                 width: "300px",
// //                 height: "400px",
// //                 position: "fixed",
// //                 bottom: "20px",
// //                 right: "20px",
// //                 backgroundColor: "white",
// //                 border: "1px solid #ccc",
// //                 padding: "10px",
// //                 borderRadius: "10px",
// //                 zIndex: "9999",
// //                 overflowY: "auto"
// //             }
// //         }, 
// //             React.createElement("h3", null, "Hey Trisha Chatbot"),
// //             React.createElement("div", { style: { height: "250px", overflowY: "auto", marginBottom: "10px" } },
// //                 messages.map((msg, index) =>
// //                     React.createElement("div", { key: index, style: { padding: "5px", backgroundColor: msg.sender === "bot" ? "#f0f0f0" : "#cce5ff", margin: "5px 0" } },
// //                         msg.text
// //                     )
// //                 )
// //             ),
// //             React.createElement("input", {
// //                 type: "text",
// //                 id: "chat-input",
// //                 value: inputText,
// //                 onChange: (e) => setInputText(e.target.value),
// //                 placeholder: "Type a message...",
// //                 style: { width: "100%", padding: "5px", marginTop: "10px" }
// //             }),
// //             React.createElement("button", { 
// //                 style: { marginTop: "10px", padding: "5px" },
// //                 onClick: handleSendMessage  // Send message when clicked
// //             }, "Send")
// //         );
// //     };

// //     ReactDOM.createRoot(chatbotRoot).render(React.createElement(Chatbot));

// //     console.log("✅ React chatbot successfully rendered!");
// // });


// document.addEventListener("DOMContentLoaded", function () {
//     console.log("✅ chatbot.js loaded...");

//     let chatbotRoot = document.getElementById("chatbot-root");
//     if (!chatbotRoot) {
//         console.error("❌ #chatbot-root div is missing!");
//         return;
//     }

//     console.log("✅ #chatbot-root found! Mounting React...");

//     const Chatbot = () => {
//         const [messages, setMessages] = React.useState([
//             { sender: "bot", text: "Hello! How can I help you today?" }
//         ]);
//         const [inputText, setInputText] = React.useState("");

//         const handleSendMessage = async () => {
//             if (!inputText.trim()) return;

//             console.log("✅ Sending message:", inputText);

//             // Add user message to chat
//             setMessages(prevMessages => [...prevMessages, { sender: "user", text: inputText }]);
//             setInputText(""); // Clear input field

//             try {
//                 // Send message to Laravel API
//                 let response = await fetch("http://localhost:8000/api/query", {
//                     method: "POST",
//                     headers: { "Content-Type": "application/json" },
//                     body: JSON.stringify({ query: inputText })
//                 });

//                 let data = await response.json();
//                 console.log("✅ API Response:", data);

//                 if (data.success && data.data) {
//                     // ✅ Convert data object/array to a string and display it
//                     let botReply = JSON.stringify(data.data, null, 2);

//                     // Add bot response with raw data
//                     setMessages(prevMessages => [...prevMessages, { sender: "bot", text: botReply }]);
//                 } else {
//                     setMessages(prevMessages => [...prevMessages, { sender: "bot", text: "No data available." }]);
//                 }
//             } catch (error) {
//                 console.error("❌ API Error:", error);
//                 setMessages(prevMessages => [...prevMessages, { sender: "bot", text: "Sorry, something went wrong!" }]);
//             }
//         };

//         return React.createElement("div", {
//             style: {
//                 width: "300px",
//                 height: "400px",
//                 position: "fixed",
//                 bottom: "20px",
//                 right: "20px",
//                 backgroundColor: "white",
//                 border: "1px solid #ccc",
//                 padding: "10px",
//                 borderRadius: "10px",
//                 zIndex: "9999",
//                 overflowY: "auto"
//             }
//         }, 
//             React.createElement("h3", null, "Hey Trisha Chatbot"),
//             React.createElement("div", { style: { height: "250px", overflowY: "auto", marginBottom: "10px", whiteSpace: "pre-wrap" } },
//                 messages.map((msg, index) =>
//                     React.createElement("div", { key: index, style: { padding: "5px", backgroundColor: msg.sender === "bot" ? "#f0f0f0" : "#cce5ff", margin: "5px 0" } },
//                         msg.text
//                     )
//                 )
//             ),
//             React.createElement("input", {
//                 type: "text",
//                 id: "chat-input",
//                 value: inputText,
//                 onChange: (e) => setInputText(e.target.value),
//                 placeholder: "Type a message...",
//                 style: { width: "100%", padding: "5px", marginTop: "10px" }
//             }),
//             React.createElement("button", { 
//                 style: { marginTop: "10px", padding: "5px" },
//                 onClick: handleSendMessage  // Send message when clicked
//             }, "Send")
//         );
//     };

//     ReactDOM.createRoot(chatbotRoot).render(React.createElement(Chatbot));

//     console.log("✅ React chatbot successfully rendered!");
// });


// document.addEventListener("DOMContentLoaded", function () {
//     console.log("✅ chatbot.js loaded...");

//     let chatbotRoot = document.getElementById("chatbot-root");
//     if (!chatbotRoot) {
//         console.error("❌ #chatbot-root div is missing!");
//         return;
//     }

//     console.log("✅ #chatbot-root found! Mounting React...");

//     const Chatbot = () => {
//         const [messages, setMessages] = React.useState([
//             { sender: "bot", text: "Hello! How can I help you today?" }
//         ]);
//         const [inputText, setInputText] = React.useState("");
//         const [isMinimized, setIsMinimized] = React.useState(false);
//         const [isTyping, setIsTyping] = React.useState(false);

//         const handleSendMessage = async () => {
//             if (!inputText.trim()) return;

//             console.log("✅ Sending message:", inputText);

//             // Add user message to chat
//             setMessages(prevMessages => [...prevMessages, { sender: "user", text: inputText }]);
//             setInputText(""); // Clear input field

//             // Show "Typing..."
//             setIsTyping(true);

//             try {
//                 let response = await fetch("http://localhost:8000/api/query", {
//                     method: "POST",
//                     headers: { "Content-Type": "application/json" },
//                     body: JSON.stringify({ query: inputText })
//                 });

//                 let data = await response.json();
//                 console.log("✅ API Response:", data);

//                 // Remove "Typing..."
//                 setIsTyping(false);

//                 if (data.success && data.data) {
//                     let botReply = JSON.stringify(data.data, null, 2);
//                     setMessages(prevMessages => [...prevMessages, { sender: "bot", text: botReply }]);
//                 } else {
//                     setMessages(prevMessages => [...prevMessages, { sender: "bot", text: "No data available." }]);
//                 }
//             } catch (error) {
//                 console.error("❌ API Error:", error);
//                 setMessages(prevMessages => [...prevMessages, { sender: "bot", text: "Sorry, something went wrong!" }]);
//                 setIsTyping(false);
//             }
//         };

//         return React.createElement("div", {
//             style: {
//                 width: isMinimized ? "300px" : "350px",
//                 height: isMinimized ? "50px" : "450px",
//                 position: "fixed",
//                 bottom: "20px",
//                 right: "20px",
//                 backgroundColor: "white",
//                 border: "1px solid #ccc",
//                 borderRadius: "10px",
//                 zIndex: "9999",
//                 overflow: "hidden",
//                 boxShadow: "0px 4px 10px rgba(0,0,0,0.1)"
//             }
//         }, 
//             // Close Button (Outside Chatbot)
//             !isMinimized && React.createElement("button", {
//                 style: {
//                     position: "absolute",
//                     top: "-10px",
//                     right: "-10px",
//                     backgroundColor: "red",
//                     color: "white",
//                     border: "none",
//                     borderRadius: "50%",
//                     width: "25px",
//                     height: "25px",
//                     cursor: "pointer",
//                     fontSize: "14px",
//                     fontWeight: "bold"
//                 },
//                 onClick: () => setIsMinimized(true)
//             }, "❌"),

//             // Header with Minimize/Maximize Button
//             React.createElement("div", {
//                 style: {
//                     backgroundColor: "#007bff",
//                     color: "white",
//                     padding: "10px",
//                     fontSize: "16px",
//                     fontWeight: "bold",
//                     display: "flex",
//                     justifyContent: "space-between",
//                     alignItems: "center",
//                     cursor: "pointer"
//                 },
//                 onClick: () => setIsMinimized(!isMinimized)
//             }, 
//                 "Hey Trisha Chatbot",
//                 React.createElement("span", { 
//                     style: { cursor: "pointer", fontSize: "18px", fontWeight: "bold" } 
//                 }, isMinimized ? "🔼" : "🔽") // Icon Toggle
//             ),

//             // Chat Content (Hidden when minimized)
//             !isMinimized && React.createElement("div", { style: { height: "300px", overflowY: "auto", padding: "10px", whiteSpace: "pre-wrap" } },
//                 messages.map((msg, index) =>
//                     React.createElement("div", {
//                         key: index,
//                         style: {
//                             display: "flex",
//                             alignItems: "center",
//                             justifyContent: msg.sender === "bot" ? "flex-start" : "flex-end",
//                             marginBottom: "10px"
//                         }
//                     },
//                         msg.sender === "bot" ?
//                             React.createElement("img", {
//                                 src: "https://img.icons8.com/ios-filled/50/000000/bot.png",
//                                 alt: "Bot",
//                                 style: { width: "25px", height: "25px", marginRight: "8px" }
//                             })
//                             :
//                             React.createElement("img", {
//                                 src: "https://img.icons8.com/ios-filled/50/000000/user.png",
//                                 alt: "User",
//                                 style: { width: "25px", height: "25px", marginLeft: "8px" }
//                             }),
//                         React.createElement("div", {
//                             style: {
//                                 padding: "8px",
//                                 backgroundColor: msg.sender === "bot" ? "#f0f0f0" : "#cce5ff",
//                                 borderRadius: "8px",
//                                 maxWidth: "70%"
//                             }
//                         }, msg.text)
//                     )
//                 ),
//                 isTyping && React.createElement("div", {
//                     style: {
//                         fontStyle: "italic",
//                         color: "#888",
//                         textAlign: "left"
//                     }
//                 }, "Typing...")
//             ),

//             // Input Box and Send Button (Hidden when minimized)
//             !isMinimized && React.createElement("div", { style: { padding: "10px", borderTop: "1px solid #ddd" } },
//                 React.createElement("input", {
//                     type: "text",
//                     id: "chat-input",
//                     value: inputText,
//                     onChange: (e) => setInputText(e.target.value),
//                     placeholder: "Type a message...",
//                     style: { width: "100%", padding: "8px", borderRadius: "5px", border: "1px solid #ccc" }
//                 }),
//                 React.createElement("button", { 
//                     style: { marginTop: "10px", padding: "8px", width: "100%", backgroundColor: "#007bff", color: "white", border: "none", borderRadius: "5px", cursor: "pointer" },
//                     onClick: handleSendMessage  
//                 }, "Send")
//             )
//         );
//     };

//     ReactDOM.createRoot(chatbotRoot).render(React.createElement(Chatbot));

//     console.log("✅ React chatbot successfully rendered!");
// });



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
            { sender: "bot", text: "Hello! How can I help you today?" }
        ]);
        const [inputText, setInputText] = React.useState("");
        const [isMinimized, setIsMinimized] = React.useState(false);
        const [isTyping, setIsTyping] = React.useState(false);

        const formatResponse = (data) => {
            if (!data) return "No data available.";

            if (Array.isArray(data)) {
                // ✅ If data is an array, format each object separately
                return data.map(item => 
                    Object.entries(item).map(([key, value]) => `${key}: ${value}`).join("\n")
                ).join("\n\n");
            } else if (typeof data === "object") {
                // ✅ If data is a single object, format it
                return Object.entries(data).map(([key, value]) => `${key}: ${value}`).join("\n");
            } else {
                // ✅ If data is a simple string or number, return it directly
                return String(data);
            }
        };

        const handleSendMessage = async () => {
            if (!inputText.trim()) return;

            console.log("✅ Sending message:", inputText);
            setMessages(prevMessages => [...prevMessages, { sender: "user", text: inputText }]);
            setInputText("");
            setIsTyping(true);

            try {
                let response = await fetch("http://localhost:8000/api/query", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ query: inputText })
                });

                let data = await response.json();
                console.log("✅ API Response:", data);

                setIsTyping(false);

                if (data.success) {
                    let formattedResponse = formatResponse(data.data);
                    setMessages(prevMessages => [...prevMessages, { sender: "bot", text: formattedResponse }]);
                } else {
                    setMessages(prevMessages => [...prevMessages, { sender: "bot", text: "No data found." }]);
                }
            } catch (error) {
                console.error("❌ API Error:", error);
                setMessages(prevMessages => [...prevMessages, { sender: "bot", text: "Sorry, something went wrong!" }]);
                setIsTyping(false);
            }
        };

        return React.createElement("div", {
            style: {
                width: "350px",
                height: isMinimized ? "50px" : "450px",
                position: "fixed",
                bottom: "20px",
                right: "20px",
                backgroundColor: "white",
                border: "1px solid #ccc",
                borderRadius: "10px",
                zIndex: "9999",
                overflow: "hidden",
                boxShadow: "0px 4px 10px rgba(0,0,0,0.1)"
            }
        }, 
            !isMinimized && React.createElement("button", {
                style: {
                    position: "absolute",
                    top: "8px",
                    right: "10px",
                    backgroundColor: "red",
                    color: "white",
                    border: "none",
                    borderRadius: "50%",
                    width: "25px",
                    height: "25px",
                    cursor: "pointer",
                    fontSize: "14px",
                    fontWeight: "bold"
                },
                onClick: () => setIsMinimized(true)
            }, "❌"),

            React.createElement("div", {
                style: {
                    backgroundColor: "#007bff",
                    color: "white",
                    padding: "10px",
                    fontSize: "16px",
                    fontWeight: "bold",
                    display: "flex",
                    justifyContent: "space-between",
                    alignItems: "center",
                    cursor: "pointer"
                },
                onClick: () => setIsMinimized(!isMinimized)
            }, 
                "Hey Trisha Chatbot",
                React.createElement("span", { 
                    style: { cursor: "pointer", fontSize: "18px", fontWeight: "bold" } 
                }, isMinimized ? "" : "")
            ),

            !isMinimized && React.createElement("div", { style: { height: "300px", overflowY: "auto", padding: "10px", whiteSpace: "pre-wrap" } },
                messages.map((msg, index) =>
                    React.createElement("div", {
                        key: index,
                        style: {
                            display: "flex",
                            alignItems: "center",
                            justifyContent: msg.sender === "bot" ? "flex-start" : "flex-end",
                            marginBottom: "10px"
                        }
                    },
                        msg.sender === "bot" ?
                            React.createElement("img", {
                                src: "/bookmyiyer/wp-content/plugins/heytrisha-woo/assets/img/boticon.jpg",
                                alt: "Bot",
                                style: { width: "25px", height: "25px", marginRight: "8px" }
                            })
                            :
                            React.createElement("img", {
                                src: "https://img.icons8.com/ios-filled/50/000000/user.png",
                                alt: "User",
                                style: { width: "25px", height: "25px", marginLeft: "8px" }
                            }),
                        React.createElement("div", {
                            style: {
                                padding: "8px",
                                backgroundColor: msg.sender === "bot" ? "#f0f0f0" : "#cce5ff",
                                borderRadius: "8px",
                                maxWidth: "70%"
                            }
                        }, msg.text)
                    )
                ),
                isTyping && React.createElement("div", {
                    style: {
                        fontStyle: "italic",
                        color: "#888",
                        textAlign: "left"
                    }
                }, "Typing...")
            ),

            !isMinimized && React.createElement("div", { style: { padding: "10px", borderTop: "1px solid #ddd" } },
                React.createElement("input", {
                    type: "text",
                    id: "chat-input",
                    value: inputText,
                    onChange: (e) => setInputText(e.target.value),
                    placeholder: "Type a message...",
                    style: { width: "100%", padding: "8px", borderRadius: "5px", border: "1px solid #ccc" }
                }),
                React.createElement("button", { 
                    style: { marginTop: "10px", padding: "8px", width: "100%", backgroundColor: "#007bff", color: "white", border: "none", borderRadius: "5px", cursor: "pointer" },
                    onClick: handleSendMessage  
                }, "Send")
            )
        );
    };

    ReactDOM.createRoot(chatbotRoot).render(React.createElement(Chatbot));

    console.log("✅ React chatbot successfully rendered!");
});

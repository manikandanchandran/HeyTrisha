import React, { useState } from 'react';
import axios from 'axios';

const Chatbot = () => {
    const [message, setMessage] = useState('');
    const [response, setResponse] = useState('');
    const [isOpen, setIsOpen] = useState(false);

    const handleSend = async () => {
        if (message.trim() === '') return;

        try {
            const result = await axios.post(window.chatbotData.apiUrl, {
                query: message
            }, {
                headers: {
                    'X-WP-Nonce': window.chatbotData.nonce
                }
            });

            setResponse(result.data.data);
        } catch (error) {
            setResponse('Error: Could not fetch data');
        }
    };

    return (
        <div>
            <div id="chatbot-icon" onClick={() => setIsOpen(!isOpen)} style={{ cursor: 'pointer' }}>
                Chatbot Icon
            </div>

            {isOpen && (
                <div id="chatbot-box">
                    <div>{response ? JSON.stringify(response) : 'Ask something!'}</div>
                    <input type="text" value={message} onChange={(e) => setMessage(e.target.value)} />
                    <button onClick={handleSend}>Send</button>
                </div>
            )}
        </div>
    );
};

// ✅ Ensure Chatbot is exported properly
export default Chatbot;

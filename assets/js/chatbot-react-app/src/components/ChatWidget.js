import React, { useState } from "react";
import { MessageCircle } from "lucide-react";
import ChatHeader from "./ChatHeader";
import ChatMessage from "./ChatMessage";
import ChatInput from "./ChatInput";

const ChatWidget = () => {
    const [isOpen, setIsOpen] = useState(false);
    const [messages, setMessages] = useState([
      { content: "Hello! How can I help you today?", sender: "bot", timestamp: new Date() },
    ]);
  
    const handleSendMessage = async (content) => {
      const userMessage = { content, sender: "user", timestamp: new Date() };
      setMessages((prev) => [...prev, userMessage]);
  
      try {
        const response = await fetch("http://localhost:8000/api/query", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ query: content }),
        });
  
        if (!response.ok) throw new Error("Failed to fetch");
  
        const data = await response.json();
        const botMessage = { content: data.reply, sender: "bot", timestamp: new Date() };
  
        setMessages((prev) => [...prev, botMessage]);
      } catch (error) {
        console.error("API Error:", error);
        setMessages((prev) => [
          ...prev,
          { content: "Error! Try again later.", sender: "bot", timestamp: new Date() },
        ]);
      }
    };
  
    console.log("ChatWidget rendered"); // Debugging line to confirm if the component renders
  
    return isOpen ? (
      <div className="fixed bottom-4 right-4 w-96 bg-white rounded-lg shadow-xl flex flex-col">
        <ChatHeader onMinimize={() => setIsOpen(false)} />
        <div className="flex-1 p-4 h-96 overflow-y-auto">
          {messages.map((message, index) => (
            <ChatMessage key={index} message={message} />
          ))}
        </div>
        <ChatInput onSendMessage={handleSendMessage} />
      </div>
    ) : (
      <button
        onClick={() => setIsOpen(true)}
        className="fixed bottom-4 right-4 bg-blue-500 text-white p-4 rounded-full shadow-lg hover:bg-blue-600"
      >
        <MessageCircle className="w-6 h-6" />
      </button>
    );
  };
  
  export default ChatWidget;
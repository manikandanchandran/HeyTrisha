import React from 'react';
import { User } from "lucide-react"; // Remove 'Bot' if not used

const ChatMessage = ({ message }) => {
  const isUser = message.sender === 'user';
  const timestamp = new Date(message.timestamp);

  return (
    <div className={`flex gap-3 ${isUser ? 'flex-row-reverse' : ''} mb-4`}>
      <div className="w-8 h-8 rounded-full flex items-center justify-center">
        {isUser ? (
          <div className="bg-blue-500 w-8 h-8 flex items-center justify-center rounded-full">
            <User className="w-5 h-5 text-white" />
          </div>
        ) : (
          <img src="/boticon.jpg" alt="Bot" className="w-8 h-9 rounded-full" />
        )}
      </div>

      <div className={`max-w-[70%] ${isUser ? 'bg-blue-500 text-white' : 'bg-gray-100'} rounded-lg p-3`}>
        <p className="text-sm">{message.content}</p>
        <span className="text-xs opacity-70 mt-1 block">
          {timestamp.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
        </span>
      </div>
    </div>
  );
};

export default ChatMessage;

import React from 'react';
import { MessageCircle, Minimize2 } from 'lucide-react';

const ChatHeader = ({ onMinimize }) => {
  return (
    <div className="bg-blue-500 text-white p-4 rounded-t-lg flex items-center justify-between">
      <div className="flex items-center gap-2">
        <MessageCircle className="w-6 h-6" />
        <h2 className="font-semibold">Hey Trisha</h2>
      </div>
      <button
        onClick={onMinimize}
        className="hover:bg-blue-600 p-1 rounded transition-colors"
      >
        <Minimize2 className="w-5 h-5" />
      </button>
    </div>
  );
};

export default ChatHeader;

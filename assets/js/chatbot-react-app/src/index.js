// import React from "react";
// import { createRoot } from "react-dom/client"; // ✅ React 18
// import ChatWidget from "./components/ChatWidget"; // Ensure this path is correct

// document.addEventListener("DOMContentLoaded", () => {
//   console.log("✅ DOM is loaded, searching for #chatbot-root...");

//   const chatbotRoot = document.getElementById("chatbot-root");

//   if (chatbotRoot) {
//     console.log("✅ #chatbot-root found! Mounting React...");
//     const root = createRoot(chatbotRoot);
//     root.render(<ChatWidget />);
//     console.log("✅ React chatbot successfully rendered!");
//   } else {
//     console.error("❌ #chatbot-root div is missing!");
//   }
// });

// console.log("✅ React: index.js is loaded");

import React from "react";
import { createRoot } from "react-dom/client";
import ChatWidget from "./components/ChatWidget"; // Import full ChatWidget component

console.log("✅ React: index.js is loaded");

// ✅ Attach ChatWidget to global `window` object
window.ChatWidget = ChatWidget;

document.addEventListener("DOMContentLoaded", () => {
    console.log("✅ DOM is loaded, searching for #chatbot-root...");

    setTimeout(() => {
        const chatbotRoot = document.getElementById("chatbot-root");

        if (chatbotRoot) {
            console.log("✅ #chatbot-root found! Mounting React...");
            const root = createRoot(chatbotRoot);
            root.render(<ChatWidget />);
            console.log("✅ React chatbot successfully rendered!");
        } else {
            console.error("❌ #chatbot-root div is missing!");
        }
    }, 1000);
});

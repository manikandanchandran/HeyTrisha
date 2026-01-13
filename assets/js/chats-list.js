document.addEventListener("DOMContentLoaded", function () {
    const root = document.getElementById("heytrisha-chats-list-root");
    if (!root) return;

    const config = window.heytrishaChatsConfig || {};
    const { restUrl, nonce, adminUrl, isArchive } = config;

    const ChatsList = () => {
        const [chats, setChats] = React.useState([]);
        const [loading, setLoading] = React.useState(true);

        React.useEffect(() => {
            loadChats();
        }, []);

        const loadChats = async () => {
            try {
                setLoading(true);
                const response = await fetch(`${restUrl}chats?archived=${isArchive ? 'true' : 'false'}`, {
                    headers: {
                        'X-WP-Nonce': nonce
                    }
                });
                const data = await response.json();
                setChats(data || []);
            } catch (error) {
                console.error('Failed to load chats:', error);
            } finally {
                setLoading(false);
            }
        };

        const archiveChat = async (chatId, archive) => {
            try {
                const response = await fetch(`${restUrl}chats/${chatId}/archive`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': nonce
                    },
                    body: JSON.stringify({ archive })
                });
                if (response.ok) {
                    loadChats();
                }
            } catch (error) {
                console.error('Failed to archive chat:', error);
            }
        };

        const deleteChat = async (chatId) => {
            if (!confirm('Are you sure you want to delete this chat?')) return;
            try {
                const response = await fetch(`${restUrl}chats/${chatId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-WP-Nonce': nonce
                    }
                });
                if (response.ok) {
                    loadChats();
                }
            } catch (error) {
                console.error('Failed to delete chat:', error);
            }
        };

        const formatDate = (dateString) => {
            const date = new Date(dateString);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        };

        if (loading) {
            return React.createElement("div", null, "Loading...");
        }

        if (chats.length === 0) {
            return React.createElement("div", { className: "heytrisha-empty-chats" },
                React.createElement("h2", null, "No chats found"),
                React.createElement("p", null, isArchive ? "You don't have any archived chats." : "Start a new chat to get started.")
            );
        }

        return React.createElement("div", { className: "heytrisha-chats-grid" },
            chats.map(chat => 
                React.createElement("div", {
                    key: chat.id,
                    className: "heytrisha-chat-card",
                    onClick: () => window.location.href = `${adminUrl}&chat_id=${chat.id}`
                },
                    React.createElement("div", { className: "heytrisha-chat-card-header" },
                        React.createElement("h3", { className: "heytrisha-chat-card-title" }, chat.title),
                        React.createElement("div", { 
                            className: "heytrisha-chat-card-actions",
                            onClick: (e) => e.stopPropagation()
                        },
                            !isArchive ? 
                                React.createElement("button", {
                                    className: "heytrisha-chat-card-action-btn",
                                    onClick: () => archiveChat(chat.id, true)
                                }, "Archive") :
                                React.createElement("button", {
                                    className: "heytrisha-chat-card-action-btn",
                                    onClick: () => archiveChat(chat.id, false)
                                }, "Unarchive"),
                            React.createElement("button", {
                                className: "heytrisha-chat-card-action-btn",
                                onClick: () => deleteChat(chat.id)
                            }, "Delete")
                        )
                    ),
                    React.createElement("div", { className: "heytrisha-chat-card-meta" },
                        React.createElement("span", null, formatDate(chat.created_at)),
                        React.createElement("span", null, `Updated: ${formatDate(chat.updated_at)}`)
                    )
                )
            )
        );
    };

    ReactDOM.render(React.createElement(ChatsList), root);
});








